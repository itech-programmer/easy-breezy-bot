<?php

namespace App\Conversations;

use App\Models\employees\Attendances;
use App\Models\employees\AttendanceReports;
use App\Models\User;
use BotMan\BotMan\BotMan;
use BotMan\BotMan\BotManFactory;
use BotMan\BotMan\Drivers\DriverManager;
use BotMan\BotMan\Messages\Attachments\File;
use BotMan\BotMan\Messages\Attachments\Image;
use BotMan\BotMan\Messages\Attachments\Location;
use BotMan\BotMan\Messages\Conversations\Conversation;
use BotMan\BotMan\Messages\Incoming\Answer;
use BotMan\BotMan\Messages\Incoming\Answer as BotManAnswer;
use BotMan\BotMan\Messages\Outgoing\Actions\Button;
use BotMan\BotMan\Messages\Outgoing\Question;
use BotMan\BotMan\Messages\Outgoing\Question as BotManQuestion;
use BotMan\Drivers\Telegram\TelegramDriver;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ComingConversation extends Conversation
{

    public function run()
    {
        $this->ask_attendance();
    }

    public function ask_attendance() {

        $user = User::where('telegram_id', $this->bot->getUser()->getId())->first();

        if($user)
        {
            $attendance = Attendances::where('employee_id', '=', $user->id)
                ->where('coming_date', '=', Carbon::now()->format('Y-m-d'))->first();

            if (empty($attendance->coming_date) and empty($attendance->coming_time)){

                return $this->attendance_coming_time($user);

            } elseif (empty($attendance->coming_latitude) and  empty($attendance->coming_longitude)) {

                $this->askForLocation('Пожалуйста, отправьте свое местоположение', function (Location $location) {
                    $user = User::where('telegram_id', $this->bot->getUser()->getId())->first();

                    $attendance = Attendances::where('employee_id', '=', $user->id)
                        ->where('coming_date', '=', Carbon::now()->format('Y-m-d'))->first();
                    $this->say($attendance);

                    return $this->attendance_coming_location($attendance, $location);
                });

            } else {
                $this->say('Вы уже прошли проверку посещаемости');
                return $this->ask_report();
            }
        }
        else
        {
            return $this->say('Извините, вы не являетесь сотрудником');
        }

    }

    public function attendance_coming_time(User $user){

        try {

            $attendance = Attendances::where('employee_id', '=', $user->id)
                ->where('coming_date', '=', Carbon::now()->format('Y-m-d'))->first();

            if (empty($attendance->coming_date) and empty($attendance->coming_time)){

                DB::beginTransaction();

                $employee_attendance = new Attendances();
                $employee_attendance->employee_id = $user->id;
                $employee_attendance->coming_date = Carbon::now()->format('Y-m-d');
                $employee_attendance->coming_time = Carbon::now()->format('H:i:s');
                $employee_attendance->save();
                $employee_attendance->toArray();

                DB::commit();

                if(!$employee_attendance){

                    $this->say('Что то пошло не так попробуйте еще раз!');
                    return $this->attendance_coming_time($user);

                } else {

                    $this->askForLocation('Пожалуйста, отправьте свое местоположение', function (Location $location) {
                        $user = User::where('telegram_id', $this->bot->getUser()->getId())->first();

                        $attendance = Attendances::where('employee_id', '=', $user->id)
                            ->where('coming_date', '=', Carbon::now()->format('Y-m-d'))->first();
                        $this->say($attendance);

                        return $this->attendance_coming_location($attendance, $location);
                    });

                }

            } else {

                $this->askForLocation('Пожалуйста, отправьте свое местоположение', function (Location $location) {
                    $user = User::where('telegram_id', $this->bot->getUser()->getId())->first();

                    $attendance = Attendances::where('employee_id', '=', $user->id)
                        ->where('coming_date', '=', Carbon::now()->format('Y-m-d'))->first();
                    $this->say($attendance);

                    return $this->attendance_coming_location($attendance, $location);
                });

            }

        } catch (\Exception $e) {
            return $this->say($e->getMessage());
        }

    }

    public function attendance_coming_location(Attendances $attendance, Location $location){

        try {

            if (empty($attendance->coming_latitude) and empty($attendance->coming_longitude)) {

                DB::beginTransaction();

                $attendance_location = Attendances::find($attendance->id);
                $attendance_location->coming_latitude = $location->getLatitude();
                $attendance_location->coming_longitude = $location->getLongitude();
                $attendance_location->save();
                $attendance_location->toArray();

                DB::commit();

                if(!$attendance_location){

                    $this->say('Что то пошло не так попробуйте еще раз!');
                    return $this->attendance_coming_location($attendance_location, $location);

                } else {

                        return $this->ask_report();
                }

            } else {

                return $this->ask_report();

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

                        return $this->ask_photo();

                    break;

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

    public function ask_photo()
    {

        $this->askForImages('Пожалуйста, пришлите фото отчет до', function ($images) {

            foreach ($images as $image) {

                $user = User::where('telegram_id', $this->bot->getUser()->getId())->first();
                $attendance = Attendances::where('employee_id', '=', $user->id)
                    ->where('coming_date', '=', Carbon::now()->format('Y-m-d'))->first();

                DB::beginTransaction();

                $image_report = new AttendanceReports();
                $image_report->attendance_id = $attendance->id;

                $url = $image->getUrl(); // The direct url

                $this->say(1 . ' - ' . 'Ссылки на ваши изображения: ' . $url);

                $image_report->file_url = $image->getUrl(); // The direct url
                $image_report->type = 'before';
                $image_report->save();
                $image_report->toArray();

                DB::commit();

                $this->say('Receives images:' . $this->bot->getMessage()->getImages());
            }

        }, function (Answer $answer) {
            $this->say('Пожалуйста, пришлите фото отчет до');
            $this->ask_photo();
        });
    }

}
