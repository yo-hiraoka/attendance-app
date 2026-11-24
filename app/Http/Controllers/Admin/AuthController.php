<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminLoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;

class AuthController extends Controller
{
    public function showLogin(Request $request): View|RedirectResponse
    {
        if ($request->user()?->isAdmin()) {
            return redirect()->route('admin.home');
        }

        return view('admin.admin-login');
    }

    public function login(
        AdminLoginRequest $request,
        AuthenticatedSessionController $fortify
    ): RedirectResponse {
        $fortify->store($request);

        return redirect()->route('admin.home');
    }

    public function home(): RedirectResponse
    {
        return redirect()->route('admin.attendance.list');
    }
}
