<?php

use Illuminate\Support\Facades\Route;

// Auth
Route::redirect('/', '/login');
Route::view('/login', 'auth.login')->name('login');
Route::view('/register', 'auth.register')->name('register');

// Halaman user
Route::view('/dashboard', 'dashboard')->name('dashboard');
Route::view('/transactions', 'transactions.index')->name('transactions.index');
Route::view('/transactions/create', 'transactions.create')->name('transactions.create');
Route::view('/transactions/categories', 'transactions.categories')->name('categories.index');
Route::view('/transfer', 'transfer')->name('transfer');
Route::view('/saving', 'saving.index')->name('saving.index');
Route::view('/saving/create', 'saving.create')->name('saving.create');
Route::view('/notifications','notifications')->name('notifications');
Route::view('/account','account')->name('account');

// Halaman admin (Backend: nanti dibatasi middleware khusus admin)
Route::redirect('/admin', '/admin/dashboard');
Route::view('/admin/dashboard', 'admin.dashboard')->name('admin.dashboard');
Route::view('/admin/users', 'admin.users')->name('admin.users');
