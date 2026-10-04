<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function index(): View
    {
        return view('dashboard', ['title' => 'Admin Dashboard']);
    }

    public function manageUsers(): View
    {
        return view('dashboard', ['title' => 'Manage Users']);
    }
}
