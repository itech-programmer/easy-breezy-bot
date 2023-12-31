<?php

namespace App\Conversations;

use App\Models\Answers;
use App\Models\AppSettings;
use App\Models\Clients;
use App\Models\services\Categories;
use App\Models\services\Orders;
use BotMan\BotMan\Messages\Attachments\Contact;
use BotMan\BotMan\Messages\Conversations\Conversation;
use BotMan\BotMan\Messages\Incoming\Answer;
use BotMan\BotMan\Messages\Incoming\IncomingMessage;
use BotMan\BotMan\Messages\Outgoing\Actions\Button;
use BotMan\BotMan\Messages\Outgoing\Question;
use BotMan\BotMan\Messages\Outgoing\Question as BotManQuestion;
use BotMan\Drivers\Telegram\Extensions\Keyboard;
use BotMan\Drivers\Telegram\Extensions\KeyboardButton;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use function Brick\Math\sum;
use function Brick\Math\toInt;
use function Termwind\ask;

class OrderConversation extends Conversation
{
    protected $rez = 0;
    /**
     * @inheritDoc
     */
    public function run()
    {
        $this->choose_category();
    }

    public function choose_category()
    {
        try {

            $categories = Categories::where('parent_id', null)->get();

            foreach ($categories as $category) {
                $button = Button::create($category->name)->value($category->id);
                $button_array[] = $button;
            }

            $services = Question::create('🧾  ️Пожалуйста, выберите тип услуг из нашего списка услуг!')
                ->callbackId('select_service')
                ->addButtons($button_array);

            $this->ask($services, function (Answer $answer) {
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

                    $this->ask($service, function (Answer $answer) {
                        if ($answer->isInteractiveMessageReply()) {
                            $this->bot->userStorage()->save([
                                'service' => $answer->getValue(),
                            ]);

                            $this->calculate($answer->getText());

                        }
                    });
                }
            });
        } catch (\Exception $e) {

            $this->say($e->getMessage());

        }

    }

    public function calculate($category) {

        $this->ask('Отправте квадрат метр место чтобы рассчитать стоимость', function (Answer $response) use ($category){
            $price = AppSettings::first();

            if (is_numeric($response->getText())) {

                if ($response->getText() != 0) {

                    $meter = $response->getText();

                    $rez = $price->price * $response->getText();

                    $this->ask_address($category, $meter, $rez);

                } else {

                    $this->calculate($category);

                }

            } else {
                $this->calculate($category);
            }

        });

    }

    public function ask_address($category, $meter, $sum) {

        $this->ask('Пожалуйста, пришлите адрес для уборки', function (Answer $response) use ($category, $meter, $sum) {

            $address = $response->getText();

            $this->set_order($category, $meter, $sum, $address);

        });

    }

    public function set_order($category, $meter, $sum, $address) {

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

    public function keyboard()
    {
        return Keyboard::create()
            ->addRow(KeyboardButton::create('📱 Отправить свой номер')->requestContact())
            ->addRow(KeyboardButton::create('📍 Отправить локацию')->requestLocation())
            ->type(Keyboard::TYPE_KEYBOARD)
            ->oneTimeKeyboard()
            ->resizeKeyboard()
            ->toArray();
    }
}
