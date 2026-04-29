<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::post('/register', [AuthController::class,'register']);
Route::post('/login', [AuthController::class,'login']);


Route::middleware('auth:sanctum')->group(function(){
    Route::get('/logout',[AuthController::class,'logout']);
    Route::get('/me',[AuthController::class,'me']);

    Route::post('/change-password', [UserController::class, 'changePassword']);
});
