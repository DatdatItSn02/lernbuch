<?php

use App\Filament\Resources\NhatKyAis\Pages\ListNhatKyAis;
use App\Filament\Resources\NhatKyAis\Pages\ViewNhatKyAi;
use App\Models\AiRequest;
use Livewire\Livewire;

// Mục tự kiểm 17 (phần Nhật ký AI): chỉ xem, mới trước, lọc, đoạn trích cắt ở bảng và đủ ở trang xem.

beforeEach(function () {
    $this->seed();
    $this->actingAs(nhanVien('admin'));
});

it('mới trước; lọc theo loại và trạng thái; lượt chạy thử để trống người học', function () {
    $hoc = nguoiHocAi(['email' => 'lan@example.com']);
    dongAi($hoc, 1, ['kind' => 'word', 'input_excerpt' => 'cu']);
    dongAi($hoc, 1, ['kind' => 'chat', 'status' => 'error', 'input_excerpt' => 'moi']);
    dongAi(null, 1, ['kind' => 'word', 'context_type' => 'prompt_test']);
    $ds = AiRequest::query()->orderBy('id')->get();

    Livewire::test(ListNhatKyAis::class)
        ->assertCanSeeTableRecords($ds->reverse()->values(), inOrder: true)
        ->assertSee('lan@example.com')->assertSee('Chạy thử')
        ->filterTable('kind', 'chat')->assertCanSeeTableRecords([$ds[1]])->assertCanNotSeeTableRecords([$ds[0], $ds[2]]);

    Livewire::test(ListNhatKyAis::class)->filterTable('status', 'error')->assertCanSeeTableRecords([$ds[1]])->assertCanNotSeeTableRecords([$ds[0]]);
});

it('đoạn trích cắt 80 ký tự ở bảng, đủ ở trang xem; không có nút tạo, sửa, xoá', function () {
    dongAi(null, 1, ['input_excerpt' => str_repeat('a', 70).str_repeat('b', 30)]);
    $dong = AiRequest::query()->first();

    Livewire::test(ListNhatKyAis::class)->assertDontSee(str_repeat('b', 30))->assertSee(str_repeat('a', 70));
    Livewire::test(ViewNhatKyAi::class, ['record' => $dong->id])->assertSee(str_repeat('b', 30));

    $this->get('/admin/nhat-ky-ai/create')->assertNotFound();
    $this->get('/admin/nhat-ky-ai/'.$dong->id.'/edit')->assertNotFound();
});
