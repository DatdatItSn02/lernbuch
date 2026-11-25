<?php

// Test cố ý hỏng để chứng minh CI chặn lỗi. Nhánh work/ci-red, không gộp vào main.
it('cố ý hỏng', function () {
    expect(1 + 1)->toBe(3);
});
