@php
    use App\Support\QuanTri\PromptAi;
    use App\Support\QuanTri\ThongKeAi;
    $o = 'padding: .5rem .75rem; text-align: right; border-bottom: 1px solid rgb(127 127 127 / .25); white-space: nowrap;';
    $oTrai = 'padding: .5rem .75rem; text-align: left; border-bottom: 1px solid rgb(127 127 127 / .25); white-space: nowrap;';
    $so = fn (int $n) => number_format($n, 0, ',', '.');
@endphp
<x-filament-panels::page>
    <p>Ngày tính theo giờ Thái Bình Dương (America/Los_Angeles), giống trần mỗi ngày của Google. Chi phí là chi phí quy đổi theo giá trả tiền.</p>

    <section aria-labelledby="tk-hom-nay">
        <h2 id="tk-hom-nay" style="font-weight: 600; font-size: 1.125rem">Hôm nay</h2>
        <dl style="display: flex; flex-wrap: wrap; gap: 2rem; margin-top: .5rem">
            <div>
                <dt>Lượt tới nhà cung cấp</dt>
                <dd style="font-size: 1.5rem; font-weight: 700">{{ $so($homNay['luot']) }} / {{ $so($homNay['tran_luot']) }}</dd>
            </div>
            <div>
                <dt>Chi phí (USD)</dt>
                <dd style="font-size: 1.5rem; font-weight: 700">{{ ThongKeAi::usd($homNay['chi_phi_micro']) }} / {{ number_format($homNay['tran_chi_phi_usd'], 2, ',', '.') }}</dd>
            </div>
        </dl>
    </section>

    <section aria-labelledby="tk-ngay" tabindex="0" style="overflow-x: auto">
        <h2 id="tk-ngay" style="font-weight: 600; font-size: 1.125rem">30 ngày gần nhất</h2>
        <table style="width: 100%; border-collapse: collapse; margin-top: .5rem; font-size: .875rem">
            <caption style="text-align: left; padding-bottom: .5rem">Mỗi dòng một ngày, mới trước. Thời gian chờ (ms) chỉ tính lượt thành công không lấy từ cache.</caption>
            <thead>
                <tr>
                    <th scope="col" style="{{ $oTrai }}">Ngày</th>
                    <th scope="col" style="{{ $o }}">Lượt</th>
                    <th scope="col" style="{{ $o }}">Thành công</th>
                    <th scope="col" style="{{ $o }}">Từ cache</th>
                    <th scope="col" style="{{ $o }}">Lỗi</th>
                    <th scope="col" style="{{ $o }}">Bị chặn</th>
                    <th scope="col" style="{{ $o }}">Hết lượt</th>
                    <th scope="col" style="{{ $o }}">Token vào</th>
                    <th scope="col" style="{{ $o }}">Token ra</th>
                    <th scope="col" style="{{ $o }}">Chi phí (USD)</th>
                    <th scope="col" style="{{ $o }}">Chờ trung bình</th>
                    <th scope="col" style="{{ $o }}">Chờ lâu nhất</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($ngay as $d)
                    <tr>
                        <th scope="row" style="{{ $oTrai }} font-weight: 400">{{ $d['ngay'] }}</th>
                        <td style="{{ $o }}">{{ $so($d['luot']) }}</td>
                        <td style="{{ $o }}">{{ $so($d['ok']) }}</td>
                        <td style="{{ $o }}">{{ $so($d['cache']) }}</td>
                        <td style="{{ $o }}">{{ $so($d['loi']) }}</td>
                        <td style="{{ $o }}">{{ $so($d['chan']) }}</td>
                        <td style="{{ $o }}">{{ $so($d['het_luot']) }}</td>
                        <td style="{{ $o }}">{{ $so($d['token_vao']) }}</td>
                        <td style="{{ $o }}">{{ $so($d['token_ra']) }}</td>
                        <td style="{{ $o }}">{{ ThongKeAi::usd($d['chi_phi_micro']) }}</td>
                        <td style="{{ $o }}">{{ $d['cho_tb'] !== null ? $so($d['cho_tb']) : '—' }}</td>
                        <td style="{{ $o }}">{{ $d['cho_max'] !== null ? $so($d['cho_max']) : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>

    <section aria-labelledby="tk-loai" tabindex="0" style="overflow-x: auto">
        <h2 id="tk-loai" style="font-weight: 600; font-size: 1.125rem">Theo loại, 7 ngày gần nhất</h2>
        @if ($loai === [])
            <p style="margin-top: .5rem">Chưa có lượt hỏi AI nào trong 7 ngày qua.</p>
        @else
            <table style="width: 100%; border-collapse: collapse; margin-top: .5rem; font-size: .875rem">
                <thead>
                    <tr>
                        <th scope="col" style="{{ $oTrai }}">Loại</th>
                        <th scope="col" style="{{ $o }}">Lượt</th>
                        <th scope="col" style="{{ $o }}">Từ cache</th>
                        <th scope="col" style="{{ $o }}">Lỗi</th>
                        <th scope="col" style="{{ $o }}">Chi phí (USD)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($loai as $d)
                        <tr>
                            <th scope="row" style="{{ $oTrai }} font-weight: 400">{{ PromptAi::tenLoai($d['kind']) }}</th>
                            <td style="{{ $o }}">{{ $so($d['luot']) }}</td>
                            <td style="{{ $o }}">{{ $so($d['cache']) }}</td>
                            <td style="{{ $o }}">{{ $so($d['loi']) }}</td>
                            <td style="{{ $o }}">{{ ThongKeAi::usd($d['chi_phi_micro']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>
</x-filament-panels::page>
