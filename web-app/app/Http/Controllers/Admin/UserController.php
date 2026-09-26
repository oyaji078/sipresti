<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(private readonly AuditService $audit) {}

    public function index(Request $request): View
    {
        $users = User::query()
            ->when($request->query('q'), function ($query, $term): void {
                $query->where(function ($q) use ($term): void {
                    $q->where('name', 'like', "%{$term}%")
                        ->orWhere('username', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%");
                });
            })
            ->when($request->query('role'), fn ($q, $v) => $q->where('role', $v))
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'filters' => $request->only(['q', 'role', 'status']),
        ]);
    }

    public function create(): View
    {
        return view('admin.users.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'username' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('users', 'username')],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')],
            'role' => ['required', Rule::enum(UserRole::class)],
            'status' => ['required', Rule::enum(UserStatus::class)],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ], [], [
            'name' => 'nama',
            'role' => 'peran',
            'status' => 'status',
            'password' => 'kata sandi',
        ]);

        $user = User::create($data);

        $this->audit->log(
            AuditLog::ACTION_USER_CREATE,
            'user',
            $user->id,
            'Menambah pengguna '.$user->username.' ('.$user->role->value.')',
        );

        return redirect()->route('admin.users.index')
            ->with('toast', ['message' => 'Pengguna '.$user->name.' berhasil ditambahkan.', 'type' => 'success']);
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', ['user' => $user]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'username' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', Rule::enum(UserRole::class)],
            'status' => ['required', Rule::enum(UserStatus::class)],
        ], [], [
            'name' => 'nama',
            'role' => 'peran',
            'status' => 'status',
        ]);

        // Administrator terakhir yang aktif tidak boleh dilepas perannya,
        // supaya sistem tidak kehilangan seluruh akses admin.
        if ($this->wouldRemoveLastAdmin($user, $data['role'], $data['status'])) {
            return back()->withInput()
                ->with('error', 'Tindakan dibatalkan: ini satu-satunya administrator aktif.');
        }

        $user->update($data);

        $this->audit->log(
            AuditLog::ACTION_USER_UPDATE,
            'user',
            $user->id,
            'Memperbarui pengguna '.$user->username,
        );

        return redirect()->route('admin.users.index')
            ->with('toast', ['message' => 'Data pengguna berhasil diperbarui.', 'type' => 'success']);
    }

    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        $next = $user->isActive() ? UserStatus::INACTIVE : UserStatus::ACTIVE;

        if ($user->id === $request->user()->id && $next === UserStatus::INACTIVE) {
            return back()->with('error', 'Anda tidak dapat menonaktifkan akun Anda sendiri.');
        }

        if ($this->wouldRemoveLastAdmin($user, $user->role->value, $next->value)) {
            return back()->with('error', 'Tindakan dibatalkan: ini satu-satunya administrator aktif.');
        }

        $user->update(['status' => $next]);

        $this->audit->log(
            AuditLog::ACTION_USER_UPDATE,
            'user',
            $user->id,
            'Mengubah status pengguna '.$user->username.' menjadi '.$next->value,
        );

        return back()->with('toast', [
            'message' => 'Pengguna '.$user->name.' kini '.$next->label().'.',
            'type' => 'success',
        ]);
    }

    public function resetPassword(User $user): RedirectResponse
    {
        // Kata sandi baru dibuat acak dan ditampilkan satu kali kepada admin,
        // bukan nilai tetap yang dapat ditebak.
        $newPassword = Str::password(12, true, true, false, false);

        $user->update(['password' => $newPassword]);

        $this->audit->log(
            AuditLog::ACTION_USER_UPDATE,
            'user',
            $user->id,
            'Reset kata sandi pengguna '.$user->username,
        );

        return back()->with('warning', 'Kata sandi baru untuk '.$user->name.': '.$newPassword.' — catat sekarang, tidak akan ditampilkan lagi.');
    }

    private function wouldRemoveLastAdmin(User $user, string $newRole, string $newStatus): bool
    {
        $stillAdmin = $newRole === UserRole::ADMIN->value && $newStatus === UserStatus::ACTIVE->value;

        if ($stillAdmin || ! $user->isAdmin() || ! $user->isActive()) {
            return false;
        }

        $activeAdmins = User::query()
            ->where('role', UserRole::ADMIN->value)
            ->where('status', UserStatus::ACTIVE->value)
            ->count();

        return $activeAdmins <= 1;
    }
}
