// Múi giờ chọn được trong Cài đặt. Máy chủ chỉ nhận tên chuẩn IANA, còn có trình duyệt vẫn trả tên cũ
// (Chrome: Asia/Saigon, Asia/Calcutta…), nên đổi tên cũ sang tên chuẩn trước khi hiện và gửi.
const TEN_CU: Record<string, string> = {
    'Asia/Saigon': 'Asia/Ho_Chi_Minh',
    'Asia/Calcutta': 'Asia/Kolkata',
    'Asia/Katmandu': 'Asia/Kathmandu',
    'Asia/Rangoon': 'Asia/Yangon',
    'Asia/Dacca': 'Asia/Dhaka',
    'Asia/Ulan_Bator': 'Asia/Ulaanbaatar',
    'Europe/Kiev': 'Europe/Kyiv',
    'America/Buenos_Aires': 'America/Argentina/Buenos_Aires',
    'America/Godthab': 'America/Nuuk',
    'Atlantic/Faeroe': 'Atlantic/Faroe',
    'Pacific/Truk': 'Pacific/Chuuk',
    'Pacific/Ponape': 'Pacific/Pohnpei',
    'Pacific/Enderbury': 'Pacific/Kanton',
};

export const tenChuan = (ten: string): string => TEN_CU[ten] ?? ten;

/** Danh sách múi giờ của trình duyệt (cũ quá thì không có) theo tên chuẩn, không trùng, luôn kèm múi giờ đang dùng. */
export function cacMuiGio(dangDung: string, cuaTrinhDuyet?: readonly string[]): string[] {
    const ds = cuaTrinhDuyet ?? (typeof Intl.supportedValuesOf === 'function' ? Intl.supportedValuesOf('timeZone') : []);
    const chuan = [...new Set(ds.map(tenChuan))].sort();
    return chuan.includes(dangDung) ? chuan : [dangDung, ...chuan];
}
