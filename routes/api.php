<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\UserController;
use App\Http\Controllers\TransactionController;



Route::post('/users', [UserController::class, 'register']);
Route::post('/login', [UserController::class, 'loginUser']);

Route::get('/', [TransactionController::class, 'showTransactions']);
Route::post('/deposit', [TransactionController::class, 'deposit']);
Route::get('/deposit', [TransactionController::class, 'showDeposits']);
Route::post('/withdrawal', [TransactionController::class, 'withdraw']);
Route::get('/withdrawal', [TransactionController::class, 'showWithdrawls']);
