<?php

namespace App\Http\Controllers;

use App\Conversations\AuthConversation;
use App\Conversations\ComingConversation;
use App\Conversations\HelpConversation;
use App\Conversations\LeaveConversation;
use App\Http\Middleware\PreventDoubleClicks;
use BotMan\BotMan\BotMan;
use BotMan\BotMan\BotManFactory;
use BotMan\BotMan\Cache\LaravelCache;
use BotMan\BotMan\Drivers\DriverManager;
use BotMan\Drivers\Telegram\TelegramDriver;
use BotMan\Drivers\Telegram\TelegramFileDriver;
use BotMan\Drivers\Telegram\TelegramLocationDriver;
use BotMan\Drivers\Telegram\TelegramPhotoDriver;

class BotManController extends Controller
{
    public function handle()
    {
        DriverManager::loadDriver(TelegramDriver::class);
        DriverManager::loadDriver( TelegramLocationDriver::class );
        DriverManager::loadDriver( TelegramPhotoDriver::class );
        DriverManager::loadDriver( TelegramFileDriver::class );

        $config = [
            'user_cache_time' => 720,

            'config' => [
                'conversation_cache_time' => 720,
            ],

            // Your driver-specific configuration
            "telegram" => [
                "token" => env('TELEGRAM_TOKEN'),
            ]
        ];

        $botman = BotManFactory::create($config, new LaravelCache());

        $botman->middleware->captured(new PreventDoubleClicks);

//        $botman->hears('/start|start', function (BotMan $bot) {
//            $bot->reply('This is a BotMan and Laravel ' . env('APP_NAME'));
//        })->stopsConversation();

        $botman->hears('/start|start', function (BotMan $bot) {
            $bot->startConversation(new AuthConversation());
        })->stopsConversation();

        $botman->hears('/boshlash|boshlash', function (BotMan $bot) {
            $bot->startConversation(new AuthConversation());
        })->stopsConversation();
//
//        $botman->hears('/keldim|keldim', function (BotMan $bot) {
//            $bot->startConversation(new ComingConversation());
//        })->stopsConversation();
//
//        $botman->hears('/ketdim|ketdim', function (BotMan $bot) {
//            $bot->startConversation(new LeaveConversation());
//        })->stopsConversation();
//
//        $botman->hears('/yordam|yordam', function (BotMan $bot) {
//            $bot->startConversation(new HelpConversation());
//        })->stopsConversation();

        $botman->listen();
    }
}
