<?php

use App\Filament\Resources\BoBaiTaps\Pages\EditBoBaiTap;
use App\Filament\Resources\BoBaiTaps\RelationManagers\CauBaiTapRelationManager;
use App\Filament\Resources\ChuDes\Pages\EditChuDe;
use App\Filament\Resources\DongTus\Pages\EditDongTu;
use App\Filament\Resources\JobLois\Pages\ListJobLois;
use App\Filament\Resources\Nguons\Pages\CreateNguon;
use App\Filament\Resources\NguPhaps\Pages\EditNguPhap;
use App\Filament\Resources\NhatKys\Pages\ViewNhatKy;
use App\Filament\Resources\Thes\Pages\EditThe;
use App\Filament\Resources\Thes\Pages\ListThes;
use App\Models\AuditLog;
use App\Models\Card;
use App\Models\ContentSource;
use App\Models\ExerciseSet;
use App\Models\FailedJob;
use App\Models\User;
use Database\Seeders\NoiDungThuSeeder;
use Filament\Forms\Components\Repeater;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

// Trang quản trị nội dung (tuần 5 G1, mục tự kiểm 10): chủ đề, thẻ, ngữ pháp, bài tập, động từ, nguồn; nhật ký thao tác; job lỗi.
// Sửa cần content.edit, đổi trạng thái cần content.publish; mọi lần sửa ghi nhật ký; thẻ riêng của người học không sửa được.

beforeEach(function () {
    $this->seed();
    $this->m = (new NoiDungThuSeeder)->tao()->taoLuyenTap();
});

it('trang nội dung cần quyền sửa nội dung; nhật ký thao tác và job lỗi cần quyền cấu hình hệ thống', function () {
    $trangNoiDung = ['chu-de', 'the', 'ngu-phap', 'bai-tap', 'dong-tu', 'nguon'];

    $this->actingAs(nhanVien('support'));
    foreach ($trangNoiDung as $trang) {
        $this->get('/admin/'.$trang)->assertForbidden();
    }

    $this->actingAs(nhanVien('content_editor'));
    foreach ($trangNoiDung as $trang) {
        $this->get('/admin/'.$trang)->assertOk();
    }
    $this->get('/admin/nhat-ky')->assertForbidden();
    $this->get('/admin/job-loi')->assertForbidden();

    // Quản trị viên có hầu hết quyền nhưng không có settings.manage (seed.sql)
    $this->actingAs(nhanVien('admin'));
    $this->get('/admin/the')->assertOk();
    $this->get('/admin/nhat-ky')->assertForbidden();

    $this->actingAs(nhanVien('super_admin'));
    $this->get('/admin/nhat-ky')->assertOk();
    $this->get('/admin/job-loi')->assertOk()->assertSee('Không có job lỗi nào');
});

it('sửa thẻ: dạng, nghĩa, câu ví dụ; ghi nhật ký từng phần; tìm được bằng nghĩa mới; biên tập viên không đổi được trạng thái', function () {
    $id = $this->m->the['das Brot'];
    // Bản dịch tiếng Trung của câu ví dụ không có trên form: câu giữ nguyên chữ thì giữ bản dịch đó
    DB::table('card_examples')->where('card_id', $id)->update(['translations' => json_encode(['vi' => 'Bánh mì vẫn còn tươi.', 'zh' => '面包还很新鲜。'], JSON_UNESCAPED_UNICODE)]);
    $bienTap = nhanVien('content_editor');
    $this->actingAs($bienTap);
    $hoanTac = Repeater::fake();

    Livewire::test(EditThe::class, ['record' => $id])
        ->assertFormSet([
            'front' => 'das Brot',
            'nghia_vi' => 'bánh mì',
            'nghia_en' => 'bread',
            'cau_vi_du' => [['sentence' => 'Das Brot ist noch frisch.', 'vi' => 'Bánh mì vẫn còn tươi.', 'en' => null]],
        ])
        ->fillForm([
            'forms' => 'die Brote (Pl.)',
            'nghia_vi' => 'bánh mì (ổ lớn)',
            'nghia_en' => '',
            'cau_vi_du' => [
                ['sentence' => 'Das Brot ist noch frisch.', 'vi' => 'Bánh mì vẫn còn tươi.', 'en' => 'The bread is still fresh.'],
                ['sentence' => 'Wir kaufen zwei Brote.', 'vi' => 'Chúng tôi mua hai ổ bánh mì.', 'en' => ''],
            ],
            'status' => 'archived',
        ])
        ->call('save')
        ->assertHasNoFormErrors();
    $hoanTac();

    $the = Card::query()->findOrFail($id);
    expect($the->forms)->toBe('die Brote (Pl.)')
        ->and($the->status)->toBe('published')
        ->and($the->version)->toBe(2)
        ->and($the->getAttribute('updated_by'))->toBe($bienTap->id)
        ->and($the->nghia())->toEqual(['vi' => 'bánh mì (ổ lớn)', 'zh' => '面包']);
    $cau = DB::table('card_examples')->where('card_id', $id)->orderBy('sort')->get();
    expect($cau->pluck('sentence')->all())->toBe(['Das Brot ist noch frisch.', 'Wir kaufen zwei Brote.'])
        ->and(json_decode($cau[0]->translations, true))->toEqual(['vi' => 'Bánh mì vẫn còn tươi.', 'zh' => '面包还很新鲜。', 'en' => 'The bread is still fresh.'])
        ->and(json_decode($cau[1]->translations, true))->toEqual(['vi' => 'Chúng tôi mua hai ổ bánh mì.']);

    expect(json_decode(dongNhatKy('card.update')['changes'], true))
        ->toEqual(['before' => ['forms' => 'die Brote', 'version' => 1], 'after' => ['forms' => 'die Brote (Pl.)', 'version' => 2]]);
    expect(json_decode(dongNhatKy('card.meanings')['changes'], true))
        ->toEqual(['before' => ['en' => 'bread', 'vi' => 'bánh mì', 'zh' => '面包'], 'after' => ['vi' => 'bánh mì (ổ lớn)', 'zh' => '面包']]);
    expect(json_decode(dongNhatKy('card.examples')['changes'], true)['after']['examples'])->toHaveCount(2);

    // Chữ để tìm tính lại: tìm bằng nghĩa mới, không dấu
    Livewire::test(ListThes::class)->searchTable('o lon')->assertCanSeeTableRecords([$the])->assertCountTableRecords(1);

    // Câu Điền câu phải có chỗ trống ____ và có đáp án; nghĩa tiếng Việt bắt buộc
    Livewire::test(EditThe::class, ['record' => $id])
        ->fillForm(['cloze' => 'Das Brot ist frisch.', 'cloze_answer' => '', 'nghia_vi' => ''])
        ->call('save')
        ->assertHasFormErrors(['cloze' => 'regex', 'nghia_vi' => 'required']);
});

it('người duyệt đổi trạng thái: thẻ ẩn thì người học không thấy, số thẻ của chủ đề đếm lại; thẻ riêng của người học không sửa được', function () {
    $id = $this->m->the['das Mehl'];
    $chuDe = (int) DB::table('cards')->where('id', $id)->value('topic_id');
    $this->actingAs(nhanVien('content_reviewer'));

    Livewire::test(EditThe::class, ['record' => $id])->fillForm(['status' => 'archived'])->call('save')->assertHasNoFormErrors();
    expect(DB::table('cards')->where('id', $id)->value('status'))->toBe('archived')
        // Bánh mì · bánh ngọt còn 4 thẻ đã xuất bản: das Brötchen, das Brot, backen, süß (der Teig là nháp)
        ->and((int) DB::table('topics')->where('id', $chuDe)->value('card_count'))->toBe(4);

    $nguoiHoc = User::factory()->create();
    $this->actingAs($nguoiHoc)->getJson('/api/v1/the/'.$id)->assertNotFound();

    $theRieng = $this->m->themThe('t-banh.v', 'der Hefezopf', 'noun', 'die Hefezöpfe', 2, 2, 2, ['vi' => 'bánh mì bện'], [], 'der', chu: $nguoiHoc->id);
    $this->actingAs(nhanVien('content_reviewer'))->get('/admin/the/'.$theRieng.'/edit')->assertNotFound();
});

it('sửa chủ đề: đổi tên, ghi nhật ký; mã và đường dẫn không đổi', function () {
    $chuDe = $this->m->chuDe['t-so.v'];
    $this->actingAs(nhanVien('content_editor'));

    Livewire::test(EditChuDe::class, ['record' => $chuDe])
        ->fillForm(['name_vi' => 'Số đếm 0–30', 'code' => 'doi-ma', 'slug' => 'doi-duong-dan'])
        ->call('save')
        ->assertHasNoFormErrors();

    $dong = DB::table('topics')->where('id', $chuDe)->first();
    expect($dong->name_vi)->toBe('Số đếm 0–30')->and($dong->code)->toBe('t-so.v')->and($dong->slug)->toBe('so-dem-tu-vung');
    expect(json_decode(dongNhatKy('topic.update')['changes'], true))->toEqual(['before' => ['name_vi' => 'Số đếm'], 'after' => ['name_vi' => 'Số đếm 0–30']]);
});

it('sửa bài ngữ pháp: nội dung sai dạng thì báo, đúng thì lưu (tăng phiên bản, ghi nhật ký) và app đọc được ngay', function () {
    $id = $this->m->bai['g-A1-23'];
    $this->actingAs(nhanVien('content_editor'));

    Livewire::test(EditNguPhap::class, ['record' => $id])
        ->fillForm(['blocks' => '[{"t": "p", "x": "thiếu ngoặc"'])
        ->call('save')
        ->assertHasFormErrors(['blocks']);
    Livewire::test(EditNguPhap::class, ['record' => $id])
        ->fillForm(['blocks' => '[{"t": "video", "x": "loại khối lạ"}]'])
        ->call('save')
        ->assertHasFormErrors(['blocks']);

    $moi = [['t' => 'p', 'x' => 'Danh từ số nhiều luôn đi với mạo từ die.'], ['t' => 'note', 'x' => 'Ghi chú mới.']];
    Livewire::test(EditNguPhap::class, ['record' => $id])
        ->fillForm(['blocks' => json_encode($moi, JSON_UNESCAPED_UNICODE)])
        ->call('save')
        ->assertHasNoFormErrors();

    $bai = DB::table('grammar_lessons')->where('id', $id)->first();
    expect(json_decode($bai->blocks, true))->toBe($moi)->and((int) $bai->version)->toBe(2);
    expect(dongNhatKy('grammar_lesson.update')['subject_id'])->toBe($id);
    $this->actingAs(User::factory()->create())->getJson('/api/v1/ngu-phap/g-A1-23')->assertOk()->assertJsonPath('data.blocks.1.x', 'Ghi chú mới.');
});

it('sửa câu bài tập: đáp án trắc nghiệm phải là một lựa chọn; lưu thì đếm lại số câu của bộ', function () {
    $bo = ExerciseSet::query()->findOrFail($this->m->bo['bt-LB3']);
    $cau = $this->m->cau['lb3:2'];
    $this->actingAs(nhanVien('content_reviewer'));

    $bang = fn () => Livewire::test(CauBaiTapRelationManager::class, ['ownerRecord' => $bo, 'pageClass' => EditBoBaiTap::class]);
    $bang()->callTableAction('edit', $cau, data: ['answers' => ['Den']])->assertHasTableActionErrors(['answers']);
    $bang()->callTableAction('edit', $cau, data: ['answers' => ['Die'], 'explanation' => 'Lampe là giống cái nên dùng die.', 'status' => 'archived'])
        ->assertHasNoErrors();

    $dong = DB::table('exercises')->where('id', $cau)->first();
    expect(json_decode($dong->answers, true))->toBe(['Die'])
        ->and($dong->explanation)->toBe('Lampe là giống cái nên dùng die.')
        ->and($dong->status)->toBe('archived')
        // Bộ bt-LB3: 4 câu đã xuất bản, ẩn một câu còn 3 (câu nháp không tính)
        ->and((int) DB::table('exercise_sets')->where('id', $bo->id)->value('item_count'))->toBe(3);
    expect(dongNhatKy('exercise.update')['subject_id'])->toBe($cau);
});

it('sửa động từ: bảng chia và nghĩa; giữ phần không có trên form, bảng để trống cả thì bỏ', function () {
    $id = (int) DB::table('verbs')->where('infinitive', 'lesen')->value('id');
    DB::table('verbs')->where('id', $id)->update(['meanings' => json_encode(['vi' => 'đọc', 'en' => 'to read', 'zh' => '读'], JSON_UNESCAPED_UNICODE)]);
    $this->actingAs(nhanVien('content_editor'));

    Livewire::test(EditDongTu::class, ['record' => $id])
        ->assertFormSet(['conjugation.pres.1' => 'liest', 'meanings.vi' => 'đọc'])
        ->fillForm([
            'level' => 2,
            'meanings' => ['vi' => 'đọc (sách, báo)', 'en' => ''],
            'conjugation' => ['imp' => ['', '', ''], 'prat' => ['las', 'last', 'las', 'lasen', 'last', 'lasen']],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $dong = DB::table('verbs')->where('id', $id)->first();
    $bang = json_decode($dong->conjugation, true);
    expect(json_decode($dong->meanings, true))->toEqual(['vi' => 'đọc (sách, báo)', 'zh' => '读'])
        ->and((int) $dong->level)->toBe(2)
        ->and(array_keys($bang))->toEqualCanonicalizing(['pres', 'prat', 'perf'])
        ->and($bang['pres'])->toBe(['lese', 'liest', 'liest', 'lesen', 'lest', 'lesen']);
    expect(dongNhatKy('verb.update')['subject_id'])->toBe($id);
});

it('thêm nguồn: mã không trùng, đúng dạng; ghi người tạo và nhật ký', function () {
    $bienTap = nhanVien('content_editor');
    $this->actingAs($bienTap);

    Livewire::test(CreateNguon::class)
        ->fillForm(['code' => 'tatoeba', 'name' => 'Trùng mã', 'commercial_use' => 'allowed'])
        ->call('create')
        ->assertHasFormErrors(['code' => 'unique']);
    Livewire::test(CreateNguon::class)
        ->fillForm(['code' => 'Sach Moi', 'name' => 'Sai dạng mã', 'commercial_use' => 'unknown'])
        ->call('create')
        ->assertHasFormErrors(['code' => 'regex']);
    Livewire::test(CreateNguon::class)
        ->fillForm(['code' => 'sach-moi', 'name' => 'Sách mới', 'license' => 'CC BY 4.0', 'commercial_use' => 'allowed', 'url' => 'https://vidu.test/sach'])
        ->call('create')
        ->assertHasNoFormErrors();

    $nguon = ContentSource::query()->where('code', 'sach-moi')->firstOrFail();
    expect($nguon->commercial_use)->toBe('allowed')->and($nguon->getAttribute('created_by'))->toBe($bienTap->id);
    expect(dongNhatKy('content_source.create')['subject_id'])->toBe($nguon->id);
});

it('nhật ký thao tác: xem danh sách và giá trị trước, sau; không có trang sửa', function () {
    $this->actingAs(nhanVien('super_admin'));
    ContentSource::query()->where('code', 'tatoeba')->firstOrFail()->update(['notes' => 'Ghi công từng câu, có link về câu gốc.']);
    $dong = AuditLog::query()->where('action', 'content_source.update')->firstOrFail();

    $this->get('/admin/nhat-ky')->assertOk()->assertSee('Sửa nguồn');
    Livewire::test(ViewNhatKy::class, ['record' => $dong->id])
        ->assertSee('Ghi công từng câu.')
        ->assertSee('Ghi công từng câu, có link về câu gốc.');
    $this->get('/admin/nhat-ky/'.$dong->id.'/edit')->assertNotFound();
});

it('job lỗi: chạy lại đưa job về hàng đợi, bỏ thì xoá; mỗi việc ghi nhật ký', function () {
    foreach (['u-1', 'u-2'] as $uuid) {
        DB::table('failed_jobs')->insert([
            'uuid' => $uuid,
            'connection' => 'database',
            'queue' => 'default',
            'payload' => json_encode(['uuid' => $uuid, 'displayName' => 'App\Jobs\ThuHangDoi', 'job' => 'Illuminate\Queue\CallQueuedHandler@call', 'attempts' => 3, 'data' => ['commandName' => 'App\Jobs\ThuHangDoi']]),
            'exception' => "RuntimeException: Lỗi thử\n#0 /app/Jobs/ThuHangDoi.php(20)",
            'failed_at' => now(),
        ]);
    }
    $this->actingAs(nhanVien('super_admin'));
    $a = FailedJob::query()->where('uuid', 'u-1')->firstOrFail();
    $b = FailedJob::query()->where('uuid', 'u-2')->firstOrFail();

    Livewire::test(ListJobLois::class)->assertCanSeeTableRecords([$a, $b])->assertSee('App\Jobs\ThuHangDoi')->assertSee('RuntimeException: Lỗi thử');
    Livewire::test(ListJobLois::class)->callTableAction('chay-lai', $a)->assertNotified('Đã đưa job về hàng đợi');
    Livewire::test(ListJobLois::class)->callTableAction('bo-qua', $b)->assertNotified('Đã bỏ job lỗi');

    expect(DB::table('failed_jobs')->count())->toBe(0)
        ->and(DB::table('jobs')->where('queue', 'default')->count())->toBe(1);
    expect(json_decode(dongNhatKy('failed_job.retry')['changes'], true)['after'])->toEqual(['uuid' => 'u-1', 'job' => 'App\Jobs\ThuHangDoi'])
        ->and(json_decode(dongNhatKy('failed_job.forget')['changes'], true)['before'])->toEqual(['uuid' => 'u-2', 'job' => 'App\Jobs\ThuHangDoi', 'exception' => 'RuntimeException: Lỗi thử']);
});
