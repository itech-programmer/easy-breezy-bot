<?php

namespace App\Http\Controllers;

use App\Models\services\Categories;
use App\Models\User;

class HomeController extends Controller
{
    public function index(){

        try {

            $data = Categories::where('parent_id', null)->get();
            return view('welcome', compact('data'));

        } catch (\Exception $e) {

            error_log($e->getMessage());

        }
    }
}
