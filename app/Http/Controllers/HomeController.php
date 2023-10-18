<?php

namespace App\Http\Controllers;

use App\Models\User;

class HomeController extends Controller
{
    public function index(){
        $data = User::orderBy('id', 'DESC')->get();
        return view('welcome', compact('data'));
    }
}
