@php
    $viThuong = 'a à á ả ã ạ ă ằ ắ ẳ ẵ ặ â ầ ấ ẩ ẫ ậ b c d đ e è é ẻ ẽ ẹ ê ề ế ể ễ ệ g h i ì í ỉ ĩ ị k l m n o ò ó ỏ õ ọ ô ồ ố ổ ỗ ộ ơ ờ ớ ở ỡ ợ p q r s t u ù ú ủ ũ ụ ư ừ ứ ử ữ ự v x y ỳ ý ỷ ỹ ỵ';
    $viHoa = 'A À Á Ả Ã Ạ Ă Ằ Ắ Ẳ Ẵ Ặ Â Ầ Ấ Ẩ Ẫ Ậ B C D Đ E È É Ẻ Ẽ Ẹ Ê Ề Ế Ể Ễ Ệ G H I Ì Í Ỉ Ĩ Ị K L M N O Ò Ó Ỏ Õ Ọ Ô Ồ Ố Ổ Ỗ Ộ Ơ Ờ Ớ Ở Ỡ Ợ P Q R S T U Ù Ú Ủ Ũ Ụ Ư Ừ Ứ Ử Ữ Ự V X Y Ỳ Ý Ỷ Ỹ Ỵ';
    $viCau = 'Học tiếng Đức mỗi ngày một chút, ôn đều thì nhớ lâu.';
    $de = 'Ich möchte zwei Brötchen. Die Straße ist schön. Äpfel, Öl, Übung, Größe, GROẞE GRÜẞE. Er sagt: „Guten Morgen!“';

    // Mỗi vai trò chữ trong DESIGN.md; test Playwright đọc data-font để biết font phải dùng.
    $vaiTro = [
        ['ten' => 'Tiêu đề thương hiệu', 'font' => 'Be Vietnam Pro', 'dam' => 800, 'lop' => 'text-display font-extrabold', 'mau' => [['vi', 'Lernbuch: học tiếng Đức'], ['de', 'Größe, Straße, GROẞE']]],
        ['ten' => 'Tiêu đề trang', 'font' => 'Be Vietnam Pro', 'dam' => 700, 'lop' => 'text-headline font-bold', 'mau' => [['vi', 'Chủ đề: Đồ ăn và thức uống'], ['de', 'Essen und Trinken in der Bäckerei']]],
        ['ten' => 'Tiêu đề mục', 'font' => 'Be Vietnam Pro', 'dam' => 700, 'lop' => 'text-title font-bold', 'mau' => [['vi', 'Bài 3: Ở tiệm bánh mì'], ['de', 'Übung 3: Grüße aus München']]],
        ['ten' => 'Chữ thường', 'font' => 'Be Vietnam Pro', 'dam' => 400, 'lop' => 'text-body', 'mau' => [['vi', $viCau], ['vi', $viThuong], ['vi', $viHoa], ['de', $de]]],
        ['ten' => 'Chữ nghiêng', 'font' => 'Be Vietnam Pro', 'dam' => 400, 'lop' => 'text-body italic', 'mau' => [['vi', 'Ví dụ: người học đọc to từng câu.'], ['de', 'Die Brötchen schmecken großartig.']]],
        ['ten' => 'Nhãn, nút, số liệu', 'font' => 'Be Vietnam Pro', 'dam' => 600, 'lop' => 'text-label font-semibold tabular-nums', 'mau' => [['vi', 'Ôn 12 thẻ · Học mới 8 từ · Chuỗi 14 ngày'], ['de', 'Übung · Größe · Grüße']]],
        ['ten' => 'Từ trên thẻ học', 'font' => 'Be Vietnam Pro', 'dam' => 800, 'lop' => 'text-study-word font-extrabold', 'mau' => [['de', 'die Straße'], ['de', 'das Brötchen']]],
        ['ten' => 'Chữ viết tay', 'font' => 'Patrick Hand', 'dam' => 400, 'lop' => 'font-hand text-hand', 'mau' => [['vi', 'Mẹo: chữ ß đọc như hai chữ s, ví dụ Straße.'], ['vi', $viThuong], ['de', 'Viele Grüße aus München!']]],
    ];
@endphp
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Kiểm chữ · Lernbuch</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-paper text-ink antialiased">
    <main class="mx-auto max-w-5xl px-5 pt-10 pb-16 sm:px-8">
        <h1 class="text-headline font-bold">Kiểm chữ</h1>
        <p class="mt-2 max-w-prose text-ink-2">Mỗi dòng ghi vai trò, font và độ đậm phải dùng. Chữ tiếng Việt đủ dấu và chữ tiếng Đức ä ö ü ß ẞ phải hiện đúng font, không lẫn font của máy.</p>

        <div class="mt-8 border-t border-line">
            @foreach ($vaiTro as $vt)
                <section class="grid gap-x-8 gap-y-3 border-b border-line py-6 md:grid-cols-[13rem_1fr]">
                    <h2 class="text-label font-semibold">
                        {{ $vt['ten'] }}
                        <span class="block font-normal text-ink-2">{{ $vt['font'] }} {{ $vt['dam'] }}</span>
                    </h2>
                    <div class="min-w-0 space-y-3">
                        @foreach ($vt['mau'] as [$lang, $chu])
                            <p lang="{{ $lang }}" class="{{ $vt['lop'] }}" data-font-sample data-font="{{ $vt['font'] }}" data-weight="{{ $vt['dam'] }}">{{ $chu }}</p>
                        @endforeach
                    </div>
                </section>
            @endforeach

            <section class="grid gap-x-8 gap-y-3 border-b border-line py-6 md:grid-cols-[13rem_1fr]">
                <h2 class="text-label font-semibold">
                    Chữ viết tay có ẞ
                    <span class="block font-normal text-ink-2">Patrick Hand không có ẞ, chữ này hiện bằng Be Vietnam Pro</span>
                </h2>
                <div class="min-w-0">
                    <p lang="de" class="font-hand text-hand" data-font-sample data-font="Patrick Hand" data-weight="400" data-fallback-font="Be Vietnam Pro" data-fallback-chars="ẞ">GROẞE GRÜẞE</p>
                </div>
            </section>
        </div>

        <p class="mt-8 text-label text-ink-2">Bản {{ config('app.release') }}</p>
    </main>
</body>
</html>
