<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
// use App\Models\User;
use App\Models\AkunPanenPoin;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Session;

class BackController extends Controller
{
    public function login(Request $request)
    {
        // 1. Validasi form
        $request->validate([
            'email' => [
                'required',
                'email',
                // 'regex:/^[a-zA-Z0-9._%+-]+@telkomsel\.co\.id$/', // hanya email @telkomsel.co.id
                'regex:/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', // semua domain
            ],
            'password' => 'required',
        ], [
            'email.regex' => 'Email harus menggunakan domain @telkomsel.co.id',
        ]);
        
            // dd('asd');
        // 2. Coba login
        if (Auth::attempt(['email_client' => $request->email, 'password' => $request->password])) {
            $user = Auth::user();
            $request->session()->regenerate();
            $email = strtolower($user->email_client ?? $user->email ?? '');
            if ($email === 'arifasep@gmail.com') {
                return redirect()->route('admin.redeems');
            }
            return redirect()->route('home'); // fallback
            
        }

        // 6. Kalau gagal login
        return back()->withErrors([
            'email' => 'Email atau Password Anda salah.',
        ])->withInput();
    }
    public function logout()
    {
        // Menghapus sesi dan logout
        Session::flush();
        Auth::logout();
        // Redirect ke halaman utama
        return redirect('/');
    }
}
