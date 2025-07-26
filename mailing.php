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
            $usersForMailing = User::where('role', 0)->get(['id', 'username']);
    
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
            $failedCount = 0;
            $reportContent = "Отчет о рассылке\n\n";
            $reportContent .= $photoId 
                ? "Тип: Изображение с подписью\nПодпись: $captionForMailing\n\n" 
                : "Тип: Текст\nТекст: $textForMailing\n\n";
            
            $successUsers = [];
            $failedUsers = [];
    
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
                    
                    if ($user->username) {
                        $userLink = "https://t.me/{$user->username}";
                    } else {
                        $userLink = "tg://user?id={$user->id} (только в Telegram)";
                    }
                    $successUsers[] = $userLink;
                } catch (\Exception $e) {
                    $failedCount++;
                    
                    if ($user->username) {
                        $userLink = "https://t.me/{$user->username}";
                    } else {
                        $userLink = "tg://user?id={$user->id} (только в Telegram)";
                    }
                    $failedUsers[] = "$userLink - " . $e->getMessage();
                }
            }
    
            $reportContent .= "Статистика:\n";
            $reportContent .= "Успешно отправлено: {$successCount}/{$totalUsers}\n";
            $reportContent .= "Не удалось отправить: {$failedCount}/{$totalUsers}\n\n";
            
            if (!empty($successUsers)) {
                $reportContent .= "Успешные отправки:\n" . implode("\n", $successUsers) . "\n\n";
            }
            
            if (!empty($failedUsers)) {
                $reportContent .= "Неудачные отправки:\n" . implode("\n", array_slice($failedUsers, 0, 50));
                if (count($failedUsers) > 50) {
                    $reportContent .= "\n... и ещё " . (count($failedUsers) - 50) . " ошибок.";
                }
            }
    
            $fileName = "mailing_report_" . time() . ".txt";
            $filePath = storage_path('app/tmp/' . $fileName);
            
            if (!file_exists(storage_path('app/tmp'))) {
                mkdir(storage_path('app/tmp'), 0755, true);
            }
            
            file_put_contents($filePath, $reportContent);
    
            $this->sendDocument([
                'chat_id' => $chatId,
                'document' => $filePath,
                'caption' => "Отчет о рассылке. Успешно: {$successCount}, Ошибок: {$failedCount}"
            ]);
    
            unlink($filePath);
    
            $this->endProcess();
            $this->storage->delete("mailing_$chatId");
    
            return;
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
