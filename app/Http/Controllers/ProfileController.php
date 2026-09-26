<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(private readonly AuditService $audit) {}

    public function edit(Request $request): View
    {
        return view('profil.edit', ['user' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'username' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
        ], [], [
            'name' => 'nama',
            'username' => 'username',
            'email' => 'email',
        ]);

        $user->update($data);

        $this->audit->log(AuditLog::ACTION_UPDATE, 'user', $user->id, 'Memperbarui profil sendiri', $user);

        return back()->with('toast', ['message' => 'Profil berhasil diperbarui.', 'type' => 'success']);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();

        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ], [
            'current_password.current_password' => 'Kata sandi saat ini tidak sesuai.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
        ], [
            'current_password' => 'kata sandi saat ini',
            'password' => 'kata sandi baru',
        ]);

        $user->update(['password' => $request->string('password')->value()]);

        $this->audit->log(AuditLog::ACTION_UPDATE, 'user', $user->id, 'Mengubah kata sandi sendiri', $user);

        return back()->with('toast', ['message' => 'Kata sandi berhasil diubah.', 'type' => 'success']);
    }
}
