<?php

namespace App\Filament\Resources\AiPrompts\Pages;

use App\Filament\Resources\AiPrompts\AiPromptResource;
use App\Models\AiPrompt;
use App\Support\QuanTri\PromptAi;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use InvalidArgumentException;

/** Một phiên bản prompt: Tạo bản mới (từ bản này), Chạy thử, Dùng bản này, Quay lại bản trước. */
class ViewAiPrompt extends ViewRecord
{
    protected static string $resource = AiPromptResource::class;

    private function prompt(): AiPrompt
    {
        /** @var AiPrompt $p */
        $p = $this->getRecord();

        return $p;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('tao-ban-moi')->label('Tạo bản mới')->icon(Heroicon::OutlinedDocumentDuplicate)
                ->modalHeading('Tạo phiên bản mới từ bản này')
                ->modalDescription('Bản mới chưa được dùng: chạy thử rồi bấm "Dùng bản này".')
                ->fillForm(fn () => [
                    'system_prompt' => $this->prompt()->system_prompt,
                    'user_template' => $this->prompt()->user_template,
                    'max_tokens' => $this->prompt()->max_tokens,
                    'temperature' => (float) $this->prompt()->temperature,
                ])
                ->schema([
                    Textarea::make('system_prompt')->label('Prompt hệ thống')->required()->maxLength(4000)->rows(8)
                        ->rule(fn () => function (string $attribute, mixed $value, Closure $fail): void {
                            $loi = PromptAi::loiHeThong((string) $value);
                            if ($loi !== null) {
                                $fail($loi);
                            }
                        }),
                    Textarea::make('user_template')->label('Mẫu câu')->required()->maxLength(1000)->rows(4)
                        ->rule(fn () => function (string $attribute, mixed $value, Closure $fail): void {
                            $loi = PromptAi::loiMauCau($this->prompt()->kind, (string) $value);
                            if ($loi !== null) {
                                $fail($loi);
                            }
                        }),
                    TextInput::make('max_tokens')->label('Token tối đa')->required()->integer()->minValue(100)->maxValue(4000),
                    TextInput::make('temperature')->label('Nhiệt độ')->required()->numeric()->minValue(0)->maxValue(1)->step(0.05),
                ])
                ->action(function (array $data): void {
                    try {
                        /** @var array{system_prompt: string, user_template: string, max_tokens: int|string, temperature: float|string} $data */
                        $moi = PromptAi::taoBanMoi($this->prompt(), $data);
                    } catch (InvalidArgumentException $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();

                        return;
                    }

                    Notification::make()->title('Đã tạo phiên bản '.$moi->version)->success()->send();
                    $this->redirect(AiPromptResource::getUrl('view', ['record' => $moi]));
                }),

            Action::make('chay-thu')->label('Chạy thử')->icon(Heroicon::OutlinedPlay)
                ->modalHeading('Chạy thử bản này')
                ->modalDescription('Gọi AI một lần bằng model mặc định; tính vào trần hệ thống, không đổi bản đang dùng.')
                ->fillForm(fn () => ['tham_so' => (string) json_encode(PromptAi::thamSoMau($this->prompt()->kind), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)])
                ->schema([
                    Textarea::make('tham_so')->label('Tham số (JSON)')->required()->rows(6)->rule('json'),
                ])
                ->action(function (array $data): void {
                    $thamSo = json_decode((string) $data['tham_so'], true);
                    $kq = PromptAi::chayThu($this->prompt(), array_map('strval', is_array($thamSo) ? $thamSo : []));
                    $tieuDe = ($kq['dung_khuon'] ? 'Đúng khuôn' : 'Không đúng khuôn').' · '.$kq['token_vao'].' token vào, '.$kq['token_ra']
                        .' ra · '.number_format($kq['chi_phi_micro'] / 1_000_000, 6, ',', '.').' USD · '.$kq['ms'].' ms · '.$kq['model'];

                    Notification::make()->title($tieuDe)
                        ->body(new HtmlString('<pre style="white-space: pre-wrap; font-size: .8rem">'.e($kq['chu']).'</pre>'))
                        ->status($kq['dung_khuon'] ? 'success' : 'warning')->persistent()->send();
                }),

            Action::make('dung')->label('Dùng bản này')->icon(Heroicon::OutlinedCheck)
                ->visible(fn () => ! $this->prompt()->is_active)
                ->requiresConfirmation()
                ->modalHeading('Dùng phiên bản này?')
                ->modalDescription('Lượt hỏi AI kế tiếp của loại này dùng bản này; bản đang dùng thôi dùng.')
                ->action(function (): void {
                    PromptAi::dung($this->prompt());
                    Notification::make()->title('Đang dùng phiên bản '.$this->prompt()->version)->success()->send();
                    $this->redirect(AiPromptResource::getUrl('view', ['record' => $this->prompt()]));
                }),

            Action::make('quay-lai')->label('Quay lại bản trước')->icon(Heroicon::OutlinedArrowUturnLeft)
                ->visible(fn () => $this->prompt()->is_active && PromptAi::banTruoc($this->prompt()->kind) !== null)
                ->requiresConfirmation()
                ->modalHeading('Quay lại phiên bản trước?')
                ->action(function (): void {
                    $truoc = PromptAi::quayLai($this->prompt()->kind);
                    Notification::make()->title('Đang dùng phiên bản '.$truoc?->version)->success()->send();

                    if ($truoc !== null) {
                        $this->redirect(AiPromptResource::getUrl('view', ['record' => $truoc]));
                    }
                }),
        ];
    }
}
