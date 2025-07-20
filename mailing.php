<?php
class CreateMessageRequest extends BaseRequest
{
    use CancelHandler, StartAndEndProcess;

    protected FileStorage $storage;

    public function trigger(): bool
    {
        $chatId = $this->update->chat()->id;
        $inputData = $this->storage->get("input_$chatId");

        return isset($this->update->message)
            && (
                (isset($this->update->message->text) && 
                !str_starts_with($this->update->message->text, '/')) ||
                isset($this->update->message->photo)
            )
            && isset($inputData['process'])
            && $inputData['process'] === static::class;
    }

    public function handle()
    {
        $chatId = $this->update->chat()->id;
        $message = $this->update->message;
        $messageText = $message->text ?? null;
        $photoArray = $message->photo ?? null;
        $caption = $message->caption ?? null;
    
        $storedData = $this->storage->get("mailing_$chatId");
        $textForMailing = $storedData['text'] ?? null;
        $photoId = $storedData['photo_id'] ?? null;
        $captionForMailing = $storedData['caption'] ?? null;
    
        if ($messageText === 'Отмена') {
            $this->endProcess();
            $this->storage->delete("mailing_$chatId");
    
            return $this->sendMessage([
                'text' => 'Рассылка отменена.',
                'reply_markup' => ['remove_keyboard' => true]
            ]);
        }
    
        if ($messageText === 'Разослать' && ($textForMailing || $photoId)) {
            $usersForMailing = User::where('role', 0)->get();
    
            if ($usersForMailing->isEmpty()) {
                $this->endProcess();
                $this->storage->delete("mailing_$chatId");
                return $this->sendMessage([
                    'text' => 'Нет пользователей для рассылки.',
                    'reply_markup' => ['remove_keyboard' => true]
                ]);
            }
    
            $totalUsers = $usersForMailing->count();
            $successCount = 0;
            $errorMessages = [];
    
            foreach ($usersForMailing as $user) {
                try {
                    if ($photoId) {
                        $this->sendPhoto([
                            'chat_id' => $user->id,
                            'photo' => $photoId,
                            'caption' => $captionForMailing,
                        ]);
                    } else {
                        $this->sendMessage([
                            'chat_id' => $user->id,
                            'text' => $textForMailing,
                        ]);
                    }
                    $successCount++;
                } catch (\Exception $e) {
                    $errorMessages[] = "Пользователь ID {$user->id}: " . $e->getMessage();
                }
            }
    
            $responseText = "Рассылка завершена.\nУспешно отправлено: {$successCount}/{$totalUsers}\n";
    
            if (!empty($errorMessages)) {
                $errorText = implode("\n", array_slice($errorMessages, 0, 5));
                if (count($errorMessages) > 5) {
                    $errorText .= "\n... и ещё " . (count($errorMessages) - 5) . " ошибок.";
                }
                $responseText .= "\nОшибки:\n" . $errorText;
            } else {
                $responseText .= "Все сообщения успешно доставлены.";
            }
    
            $this->endProcess();
            $this->storage->delete("mailing_$chatId");
    
            return $this->sendMessage([
                'text' => $responseText,
                'reply_markup' => ['remove_keyboard' => true]
            ]);
        }
    
        if ($textForMailing || $photoId) {
            $responseText = "Данные для рассылки установлены:\n\n";
            $responseText .= $photoId 
                ? "Изображение с подписью: $captionForMailing" 
                : "Текст: $textForMailing";
    
            return $this->sendMessage([
                'text' => $responseText . "\n\nВыберите действие:",
                'reply_markup' => [
                    'keyboard' => [
                        [['text' => 'Разослать']],
                        [['text' => 'Отмена']]
                    ],
                    'resize_keyboard' => true,
                    'one_time_keyboard' => true,
                ]
            ]);
        }
    
        if ($photoArray) {
            if (empty($caption)) {
                return $this->sendMessage([
                    'text' => 'Пожалуйста, добавьте подпись к изображению.'
                ]);
            }
    
            $photo = end($photoArray);
            $this->storage->set("mailing_$chatId", [
                'photo_id' => $photo->file_id,
                'caption' => $caption
            ]);
    
            return $this->sendMessage([
                'text' => "Вы хотите разослать это изображение с подписью?\n\n$caption",
                'reply_markup' => [
                    'keyboard' => [
                        [['text' => 'Разослать']],
                        [['text' => 'Отмена']]
                    ],
                    'resize_keyboard' => true,
                    'one_time_keyboard' => true,
                ]
            ]);
        } else {
            $validator = Validator::make(['text' => $messageText], [
                'text' => 'required|string'
            ]);
    
            if ($validator->fails()) {
                return $this->sendMessage([
                    'text' => 'Введите корректный текст для рассылки.'
                ]);
            }
    
            $this->storage->set("mailing_$chatId", ['text' => $messageText]);
            return $this->sendMessage([
                'text' => "Вы хотите разослать этот текст?\n\n$messageText",
                'reply_markup' => [
                    'keyboard' => [
                        [['text' => 'Разослать']],
                        [['text' => 'Отмена']]
                    ],
                    'resize_keyboard' => true,
                    'one_time_keyboard' => true,
                ]
            ]);
        }
    }
}