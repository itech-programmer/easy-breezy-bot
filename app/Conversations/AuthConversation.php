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

            $user = User::where('telegram_id', $this->bot->getUser()->getId())->first();

            $attendance = Attendances::where('employee_id', '=', $user->id)
                ->where('coming_date', '=', Carbon::now()->format('Y-m-d'))->first();

            if($user)
            {
                if (empty($attendance->coming_date) and empty($attendance->coming_time) and empty($attendance->coming_latitude) and empty($attendance->coming_longitude)){

                    $question = BotManQuestion::create('Добрый день ' . $user->full_name)
                        ->addButtons([
                            Button::create('Keldim')
                                ->value('keldim'),
                            Button::create('Yordam')
                                ->value('yordam'),
                        ]);

                } else {

                    $question = BotManQuestion::create('Добрый день ' . $user->full_name)
                        ->addButtons([
                            Button::create('Ketdim')
                                ->value('ketdim'),
                            Button::create('Yordam')
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
            }
            else
            {
                return $this->say('no ' . $this->bot->getUser()->getId());
            }

        } catch (\Exception $e) {

            $this->say($e->getMessage());

        }

    }


}
