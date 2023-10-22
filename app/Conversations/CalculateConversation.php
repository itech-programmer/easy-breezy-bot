<?php

namespace App\Conversations;

use App\Models\AppSettings;
use App\Models\Clients;
use App\Models\employees\Attendances;
use App\Models\services\Categories;
use App\Models\services\Orders;
use App\Models\User;
use BotMan\BotMan\Messages\Attachments\Location;
use BotMan\BotMan\Messages\Conversations\Conversation;
use BotMan\BotMan\Messages\Incoming\Answer;
use BotMan\BotMan\Messages\Outgoing\Actions\Button;
use BotMan\BotMan\Messages\Outgoing\Question;
use Carbon\Carbon;
use Haruncpi\LaravelIdGenerator\IdGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class CalculateConversation extends Conversation
{

    /**
     * @inheritDoc
     */
    public function run()
    {
        $this->calculate();
    }


    public function calculate()
    {

        $this->ask('Отправте квадрат метр место чтобы рассчитать стоимость', function (Answer $response) {
            $price = AppSettings::first();

            if (is_numeric($response->getText())) {

                if ($response->getText() != 0) {

                       $meter = $response->getText();

                       $rez = $price->price * $response->getText();

                    $order = Question::create('🧾  ️ Стоимость будет составлять ' . $rez . ' Сум' . "\nПродолжит заказ ?")
                        ->callbackId('accept')
                        ->addButtons([
                            Button::create('Да')->value('yes'),
                            Button::create('Нет!')->value('no'),
                        ]);

                    $this->ask($order, function (Answer $answer)  use ($rez, $meter){
                        // Detect if button was clicked:
                        if ($answer->isInteractiveMessageReply()) {
                            if ($answer->getValue() == 'yes') {
                                $this->set_order($rez, $meter);
                            } else {
                                $this->say('Мы будем рады вас видеть еще !');
                            }
                        }
                    });

                } else {

                    $this->calculate();

                }

            } else {
                $this->calculate();
            }

        });

    }

    public function set_order($rez, $meter) {

        try {

            $categories = Categories::where('parent_id', null)->get();

            foreach ($categories as $category) {
                $button = Button::create($category->name)->value($category->id);
                $button_array[] = $button;
            }

            $services = Question::create('🧾  ️Пожалуйста, выберите тип услуг из нашего списка услуг!')
                ->callbackId('select_service')
                ->addButtons($button_array);

            $this->ask($services, function (Answer $answer) use ($rez, $meter){
                if ($answer->isInteractiveMessageReply()) {
                    $this->bot->userStorage()->save([
                        'service' => $answer->getValue(),
                    ]);

                    $parent = Categories::find($answer->getValue());

                    $sub_categories = Categories::where('parent_id', $answer->getValue())->get();

                    foreach ($sub_categories as $category) {
                        $button = Button::create($category->name)->value($category->id);
                        $button_arr[] = $button;
                    }

                    $service = Question::create('🧾  ️ ' . $parent->name)
                        ->callbackId('select_service')
                        ->addButtons($button_arr);

                    $this->ask($service, function (Answer $answer) use ($rez, $meter){
                        if ($answer->isInteractiveMessageReply()) {
                            $this->bot->userStorage()->save([
                                'service' => $answer->getValue(),
                            ]);

                            $this->ask_address($answer->getValue(), $meter, $rez);

                        }
                    });
                }
            });
        } catch (\Exception $e) {

            $this->say($e->getMessage());

        }

    }

    public function ask_address($category, $meter, $sum) {

        $this->ask('Пожалуйста, пришлите адрес для уборки', function (Answer $response) use ($category, $meter, $sum) {

            $address = $response->getText();

            $this->store($category, $meter, $sum, $address);

        });

    }

    public function store($category, $meter, $sum, $address) {

        try {

            DB::beginTransaction();

            $client = new Clients();
            $client->telegram_id = $this->bot->getUser()->getId();
            $client->full_name = $this->bot->getUser()->getLastName() . ' ' . $this->bot->getUser()->getFirstName();
            $client->username = $this->bot->getUser()->getUsername();
            $client->save();
            $client->toArray();

            $set_order = new Orders();
            $set_order->order_id = $this->generate_order_id();
            $set_order->client_id = $client->id;
            $set_order->category_id = $category;
            $set_order->address = $address;
            $set_order->square_meter = $meter;
            $set_order->price = $sum;
            $set_order->save();
            $set_order->toArray();

            DB::commit();

            if(!$set_order and !$client){

                $this->say('Что то пошло не так попробуйте еще раз!');
                return $this->store($category, $sum, $meter);

            } else {

                return $this->say('Ваш заказ принять');

            }
        } catch (\Exception $e) {
            return $this->say($e->getMessage());
        }

    }

    public function generate_order_id()
    {
        $order = DB::table('orders')->select('order_id')->latest('id')->first();
        if ($order) {
            $order_id = $order->order_id;
            $remove_first_char = substr($order_id, 1);
            $generate_order_id = 'EBO-' . str_pad($remove_first_char + 1, 8, "0", STR_PAD_LEFT);
        } else {
            $generate_order_id = 'EBO-' . str_pad(1, 8, "0", STR_PAD_LEFT);
        }
        return $generate_order_id;
    }
}
