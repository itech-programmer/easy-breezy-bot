<?php

namespace App\Conversations;

use App\Models\services\Categories;
use BotMan\BotMan\Messages\Conversations\Conversation;
use BotMan\BotMan\Messages\Outgoing\Actions\Button;
use BotMan\BotMan\Messages\Outgoing\Question as BotManQuestion;

class OrderConversation extends Conversation
{

    /**
     * @inheritDoc
     */
    public function run()
    {
        $this->set_order();
    }

    public function set_order(){

        try {

            $categories = Categories::where('parent_id', null)->get();

            $categories_template = BotManQuestion::create("➡️ Наши услуги");

            foreach ($categories as $category) {
                $categories_template->addButton(Button::create($category->name)
                    ->value($category->id)->additionalParameters(['parse_mode' => 'Markdown']));
            }

        } catch (\Exception $e) {

            $this->say($e->getMessage());

        }
    }
}
