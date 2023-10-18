<?php


namespace App\Conversations;


use App\Models\employees\Attendances;
use App\Models\employees\HelpReports;
use App\Models\User;
use BotMan\BotMan\Messages\Attachments\Location;
use BotMan\BotMan\Messages\Conversations\Conversation;
use BotMan\BotMan\Messages\Incoming\Answer as BotManAnswer;
use BotMan\BotMan\Messages\Outgoing\Actions\Button;
use BotMan\BotMan\Messages\Outgoing\Question as BotManQuestion;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class HelpConversation extends Conversation
{

    public function run()
    {
        $this->ask_help();
    }

    public function ask_help() {

        $question = BotManQuestion::create('Пожалуйста, выбирите помощь')
            ->addButtons([
                Button::create('Сбой оборудования')
                    ->value('hardware_failure'),
                Button::create('Прочее')
                    ->value('other'),
            ]);

        $this->ask($question, function (BotManAnswer $answer) {
            switch ($answer->getValue()) {

                case 'hardware_failure':
                    $user = User::where('telegram_id', $this->bot->getUser()->getId())->first();
                    return $this->ask_hardware($user);

                    break;

                case 'other':
                    $user = User::where('telegram_id', $this->bot->getUser()->getId())->first();
                    return $this->ask_other($user);

                default:

                    return $this->ask_help();

            }
        });
    }

    public function ask_hardware(User $user){

        try {

                DB::beginTransaction();

                $employee_attendance = new HelpReports();
                $employee_attendance->employee_id = $user->id;
                $employee_attendance->help_type = 'hardware';
                $employee_attendance->asking_date = Carbon::now()->format('Y-m-d');
                $employee_attendance->asking_time = Carbon::now()->format('H:i:s');
                $employee_attendance->save();
                $employee_attendance->toArray();

                DB::commit();

                if(!$employee_attendance){

                    $this->say('Что то пошло не так попробуйте еще раз!');
                    return $this->ask_hardware($user);

                } else {

                    return $this->say('Через минуту с вами связаться!');

                }

        } catch (\Exception $e) {
            return $this->say($e->getMessage());
        }

    }

    public function ask_other(User $user){

        try {

            DB::beginTransaction();

            $employee_attendance = new HelpReports();
            $employee_attendance->employee_id = $user->id;
            $employee_attendance->help_type = 'other';
            $employee_attendance->asking_date = Carbon::now()->format('Y-m-d');
            $employee_attendance->asking_time = Carbon::now()->format('H:i:s');
            $employee_attendance->save();
            $employee_attendance->toArray();

            DB::commit();

            if(!$employee_attendance){

                $this->say('Что то пошло не так попробуйте еще раз!');
                return $this->ask_hardware($user);

            } else {

                return $this->say('Через минуту с вами связаться!');

            }

        } catch (\Exception $e) {
            return $this->say($e->getMessage());
        }

    }

}
