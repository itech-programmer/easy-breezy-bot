<?php

namespace App\Conversations;

use App\Models\User;
use BotMan\BotMan\Messages\Conversations\Conversation;
use BotMan\BotMan\Messages\Incoming\Answer as BotManAnswer;
use BotMan\BotMan\Messages\Outgoing\Actions\Button;
use BotMan\BotMan\Messages\Outgoing\Question as BotManQuestion;

class AuthConversation extends Conversation
{
    public function run()
    {
        $this->auth();
    }

    public function auth() {

        try {

            $user = User::where('telegram_id', $this->bot->getUser()->getId())->first();

            if($user)
            {
                $question = BotManQuestion::create('Добрый день ' . $user->full_name)
                    ->addButtons([
                        Button::create('Keldim')
                            ->value('keldim'),
                        Button::create('Yordam')
                            ->value('yordam'),
                        Button::create('Ketdim')
                            ->value('ketdim'),
                    ]);

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
