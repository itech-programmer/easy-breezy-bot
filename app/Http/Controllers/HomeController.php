<?php

namespace App\Http\Controllers;

use App\Models\services\Categories;
use App\Models\User;
use Haruncpi\LaravelIdGenerator\IdGenerator;

class HomeController extends Controller
{
    public function index(){

        try {

            $data = IdGenerator::generate(['table' => 'orders', 'field'=>'order_id', 'length' => 10, 'prefix' =>date("EBO-")]);
            //output: EBO-00001
            return view('welcome', compact('data'));

        } catch (\Exception $e) {

            error_log($e->getMessage());

        }
    }
}
