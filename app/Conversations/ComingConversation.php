<?php

namespace App\Conversations;

use App\Models\employees\Attendances;
use App\Models\User;
use BotMan\BotMan\Messages\Attachments\Location;
use BotMan\BotMan\Messages\Conversations\Conversation;
use BotMan\BotMan\Messages\Incoming\Answer as BotManAnswer;
use BotMan\BotMan\Messages\Outgoing\Actions\Button;
use BotMan\BotMan\Messages\Outgoing\Question;
use BotMan\BotMan\Messages\Outgoing\Question as BotManQuestion;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ComingConversation extends Conversation
{

    public function run()
    {
        $this->auth();
    }

    public function auth() {

        $user = User::where('telegram_id', $this->bot->getUser()->getId())->first();

        if($user)
        {
            $attendance = Attendances::where('employee_id', '=', $user->id)
                ->where('coming_date', '=', Carbon::now()->format('Y-m-d'))->first();
            if (empty($attendance->coming_date) and empty($attendance->coming_time)){
                return $this->coming_attendance($user);
            } else {
                return $this->say('Вы уже прошли проверку посещаемости');
            }
        }
        else
        {
            return $this->say($this->bot->getUser()->getId());
        }
    }

    public function coming_attendance(User $user){

        try {

            DB::beginTransaction();

            $attendance = new Attendances();
            $attendance->employee_id = $user->id;
            $attendance->coming_date = Carbon::now()->format('Y-m-d');
            $attendance->coming_time = Carbon::now()->format('H:i:s');
            $attendance->save();
            $attendance->toArray();

            DB::commit();

            if(!$attendance){
                $this->say('Что то пошло не так попробуйте еще раз!');
                return $this->coming_attendance($user);
            }else{
                $this->askForLocation('Пожалуйста, отправьте свое местоположение', function (Location $location) {
                    $user = User::where('telegram_id', $this->bot->getUser()->getId())->first();
                    $attendance = Attendances::where('employee_id', '=', $user->id)
                        ->where('coming_date', '=', Carbon::now()->format('Y-m-d'))->first();
                    $this->say($attendance);
                    return $this->set_location($attendance, $location);
                });
            }

        } catch (\Exception $e) {

            $this->say($e->getMessage());
        }
    }

    public function set_location(Attendances $attendance, Location $location){

        try {

            DB::beginTransaction();

            $attendance = Attendances::find($attendance->id);
            $attendance->coming_latitude = $location->getLatitude();
            $attendance->coming_longitude = $location->getLongitude();
            $attendance->save();
            $attendance->toArray();

            DB::commit();

            if(empty($attendance->coming_latitude) and empty($attendance->coming_longitude)){
                $this->askForLocation('Что то пошло не так, отправьте свое местоположение еще раз!', function (Location $location) {
                    $user = User::where('telegram_id', $this->bot->getUser()->getId())->first();
                    $attendance = Attendances::where('employee_id', '=', $user->id)
                        ->where('coming_date', '=', Carbon::now()->format('Y-m-d'))->first();
                    return $this->set_location($attendance, $location);
                });
            }else{
//                return $this->ask_report();
            }

        } catch (\Exception $e) {

            $this->say($e->getMessage());

        }
    }

    public function ask_report(){

        $question = BotManQuestion::create('Пожалуйста, пришлите фото или видео отчет до')
            ->addButtons([
                Button::create('Фото')
                    ->value('foto'),
                Button::create('Видео')
                    ->value('video'),
            ]);

        $this->ask($question, function (BotManAnswer $answer) {
            switch ($answer->getValue()) {
                case 'foto':

                    $user = User::where('telegram_id', $this->bot->getUser()->getId())->first();
                    $attendance = Attendances::where('employee_id', '=', $user->id)
                        ->where('coming_date', '=', Carbon::now()->format('Y-m-d'))->first();

                    return $this->set_photo_before($attendance);
                case 'video':

                    $user = User::where('telegram_id', $this->bot->getUser()->getId())->first();
                    $attendance = Attendances::where('employee_id', '=', $user->id)
                        ->where('coming_date', '=', Carbon::now()->format('Y-m-d'))->first();

                    return $this->set_video_before($attendance);
                default:
                    return $this->ask_report();
            }
        });
    }

//    public function set_photo_before(Attendances $attendance){

//        $this->askForImages('Пожалуйста, отправьте фотоотчет!', function ($images) {
//            $user = User::where('telegram_id', $this->bot->getUser()->getId())->first();
//
//        });

//        return $this->set_photo_before($attendance);
//    }

//    public function set_video_before(Attendances $attendance){
//        $this->askForVideos('Пожалуйста, отправьте видеоотчет!', function ($videos) {
//        $user = User::where('telegram_id', $this->bot->getUser()->getId())->first();
//
//        });

//        return $this->set_video_before($attendance);
//    }
}
