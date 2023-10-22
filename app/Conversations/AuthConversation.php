<?php

namespace App\Conversations;

use App\Models\employees\Attendances;
use App\Models\User;
use BotMan\BotMan\Messages\Conversations\Conversation;
use BotMan\BotMan\Messages\Incoming\Answer as BotManAnswer;
use BotMan\BotMan\Messages\Outgoing\Actions\Button;
use BotMan\BotMan\Messages\Outgoing\Question as BotManQuestion;
use Carbon\Carbon;

class AuthConversation extends Conversation
{
    public function run()
    {
        $this->auth();
    }

    public function auth() {

        try {

            if (User::where('telegram_id', '=', $this->bot->getUser()->getId())->exists()) {

                $user = User::where('telegram_id', $this->bot->getUser()->getId())->first();

                $attendance = Attendances::where('employee_id', '=', $user->id)
                    ->where('coming_date', '=', Carbon::now()->format('Y-m-d'))->first();

                if (empty($attendance->coming_date) and empty($attendance->coming_time) and empty($attendance->coming_latitude) and empty($attendance->coming_longitude)){

                    $question = BotManQuestion::create('Добрый день ' . $user->full_name)
                        ->addButtons([
                            Button::create('Келдим')
                                ->value('keldim'),
                            Button::create('Ёрдам')
                                ->value('yordam'),
                        ]);

                } else if (empty($attendance->leaving_date) and empty($attendance->leaving_time) and empty($attendance->leaving_latitude) and empty($attendance->leaving_longitude)){

                    $question = BotManQuestion::create('Добрый день ' . $user->full_name)
                        ->addButtons([
                            Button::create('Кетдим')
                                ->value('ketdim'),
                            Button::create('Ёрдам')
                                ->value('yordam'),

                        ]);

                } else {

                    $question = BotManQuestion::create('Добрый день ' . $user->full_name)
                        ->addButtons([
                            Button::create('Ёрдам')
                                ->value('yordam'),

                        ]);

                }

                $this->ask($question, function (BotManAnswer $answer) {
                    switch ($answer->getValue()) {
                        case 'keldim':
                            return $this->bot->startConversation(new ComingConversation());
                        case 'yordam':
                            return $this->bot->startConversation(new HelpConversation());
                        case 'ketdim':
                            return $this->bot->startConversation(new LeaveConversation());
                        default:
                            return $this->auth();
                    }
                });

            } else {

                $question = BotManQuestion::create('Салом ' . $this->bot->getUser()->getUsername())
                    ->addButtons([
                        Button::create('Буюртма бериш')
                            ->value('order'),
                        Button::create('Хисоблаш')
                            ->value('calculate'),
                        Button::create('Ёрдам')
                            ->value('help'),
                    ]);

                $this->ask($question, function (BotManAnswer $answer) {
                    switch ($answer->getValue()) {
                        case 'order':
                            return $this->bot->startConversation(new OrderConversation());
                        case 'calculate':
                            return $this->bot->startConversation(new CalculateConversation());
                        case 'help':
                            if (User::where('telegram_id', '=', $this->bot->getUser()->getId())->exists()) {

                                return $this->bot->startConversation(new HelpConversation());

                            } else {

                                return $this->say('Easy Breezy');
                            }

                        default:
                            return $this->auth();
                    }
                });

            }

        } catch (\Exception $e) {

            $this->say($e->getMessage());

        }
    }


}
