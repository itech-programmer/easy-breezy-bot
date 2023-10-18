<?php

namespace App\Conversations;

use App\Models\employees\AttendanceReports;
use App\Models\employees\Attendances;
use App\Models\User;
use BotMan\BotMan\Messages\Attachments\Location;
use BotMan\BotMan\Messages\Conversations\Conversation;
use BotMan\BotMan\Messages\Incoming\Answer;
use BotMan\BotMan\Messages\Incoming\Answer as BotManAnswer;
use BotMan\BotMan\Messages\Outgoing\Actions\Button;
use BotMan\BotMan\Messages\Outgoing\Question as BotManQuestion;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LeaveConversation extends Conversation
{
    /** @var integer */
    protected $counter = 0;

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

            if (empty($attendance->leaving_date) and empty($attendance->leaving_time)){

                return $this->attendance_leaving_time($user);

            } elseif (empty($attendance->leaving_latitude) and  empty($attendance->leaving_longitude)) {

                $this->askForLocation('Пожалуйста, отправьте свое местоположение', function (Location $location) {
                    $user = User::where('telegram_id', $this->bot->getUser()->getId())->first();

                    $attendance = Attendances::where('employee_id', '=', $user->id)
                        ->where('coming_date', '=', Carbon::now()->format('Y-m-d'))->first();
                    $this->say($attendance);

                    return $this->attendance_leaving_location($attendance, $location);
                });

            } else {
                $this->say('Вы уже прошли проверку посещаемости');
//                return $this->ask_report();
                return $this->ask_photo();
            }
        }
        else
        {
            return $this->say('Извините, вы не являетесь сотрудником');
        }

    }

    public function attendance_leaving_time(User $user){

        try {

            $attendance = Attendances::where('employee_id', '=', $user->id)
                ->where('coming_date', '=', Carbon::now()->format('Y-m-d'))->first();

            if (empty($attendance->leaving_date) and empty($attendance->leaving_time)){

                DB::beginTransaction();

                $employee_attendance = Attendances::find($attendance->id);
                $employee_attendance->employee_id = $user->id;
                $employee_attendance->leaving_date = Carbon::now()->format('Y-m-d');
                $employee_attendance->leaving_time = Carbon::now()->format('H:i:s');
                $employee_attendance->save();
                $employee_attendance->toArray();

                DB::commit();

                if(!$employee_attendance){

                    $this->say('Что то пошло не так попробуйте еще раз!');
                    return $this->attendance_leaving_time($user);

                } else {

                    $this->askForLocation('Пожалуйста, отправьте свое местоположение', function (Location $location) {
                        $user = User::where('telegram_id', $this->bot->getUser()->getId())->first();

                        $attendance = Attendances::where('employee_id', '=', $user->id)
                            ->where('coming_date', '=', Carbon::now()->format('Y-m-d'))->first();
                        $this->say($attendance);

                        return $this->attendance_leaving_location($attendance, $location);
                    });

                }

            } else {

                $this->askForLocation('Пожалуйста, отправьте свое местоположение', function (Location $location) {
                    $user = User::where('telegram_id', $this->bot->getUser()->getId())->first();

                    $attendance = Attendances::where('employee_id', '=', $user->id)
                        ->where('coming_date', '=', Carbon::now()->format('Y-m-d'))->first();
                    $this->say($attendance);

                    return $this->attendance_leaving_location($attendance, $location);
                });

            }

        } catch (\Exception $e) {
            return $this->say($e->getMessage());
        }

    }

    public function attendance_leaving_location(Attendances $attendance, Location $location){

        try {

            if (empty($attendance->leaving_latitude) and empty($attendance->leaving_longitude)) {

                DB::beginTransaction();

                $attendance_location = Attendances::find($attendance->id);
                $attendance_location->leaving_latitude = $location->getLatitude();
                $attendance_location->leaving_longitude = $location->getLongitude();
                $attendance_location->save();
                $attendance_location->toArray();

                DB::commit();

                if(!$attendance_location){

                    $this->say('Что то пошло не так попробуйте еще раз!');
                    return $this->attendance_leaving_location($attendance_location, $location);

                } else {

//                return $this->ask_report();
                    return $this->ask_photo();
                }

            } else {

//                return $this->ask_report();
                return $this->ask_photo();

            }


        } catch (\Exception $e) {
            $this->say($e->getMessage());
        }

    }

    public function ask_report(){

        $question = BotManQuestion::create('Пожалуйста, пришлите фото или видео отчет после')
            ->addButtons([
                Button::create('Фото')
                    ->value('foto'),
//                Button::create('Видео')
//                    ->value('video'),
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

                    return $this->set_video_after($attendance);

                default:

                    return $this->ask_report();

            }
        });
    }

    public function ask_photo()
    {
        $i = $this->counter+1;
        $this->askForImages('Пожалуйста, пришлите фото отчет после', function ($images) {

            foreach ($images as $image) {

                $user = User::where('telegram_id', $this->bot->getUser()->getId())->first();
                $attendance = Attendances::where('employee_id', '=', $user->id)
                    ->where('coming_date', '=', Carbon::now()->format('Y-m-d'))->first();

                DB::beginTransaction();

                $image_report = new AttendanceReports();
                $image_report->attendance_id = $attendance->id;

                $url = $image->getUrl(); // The direct url

//                $this->bot->reply(1 . ' - ' . 'Ссылки на ваши изображения: ' . $url);
//                $this->counter = $images->count();

                $report = $url;

                $image_report->file_url = $image->getUrl(); // The direct url
                $image_report->type = 'after';
                $image_report->save();
                $image_report->toArray();

                DB::commit();

                $this->say('Receives images:' . $this->bot->getMessage()->getImages());

//                $this->say(1 . ' - ' . 'изображения: ' . $this->counter += 1);
            }

        }, function (Answer $answer) {
            $this->say('Пожалуйста, пришлите фото отчет после');
            $this->ask_photo();
        });
    }
}
