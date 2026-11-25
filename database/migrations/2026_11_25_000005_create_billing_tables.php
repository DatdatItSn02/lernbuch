<?php

// File sinh tự động bởi tools/schema/gen_migrations.py từ database/sql/schema.sql.
// Không sửa tay: muốn đổi cấu trúc thì viết migration mới.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entitlements', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->string('code', 40)->charset('ascii')->collation('ascii_bin')->comment('levels, modes, ai_daily, offline, exam_prep, custom_decks');
            $table->string('name', 120);
            $table->string('value_type', 10)->charset('ascii')->collation('ascii_bin')->comment('bool, int, list');
            $table->json('default_value')->nullable()->comment('giá trị cho khách chưa đăng nhập');
            $table->string('description', 255)->nullable();
            $table->smallInteger('sort')->default(0);
            $table->unsignedInteger('created_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedInteger('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->primary(['code']);
            $table->index(['created_by'], 'ix_entitlements_created_by');
            $table->index(['updated_by'], 'ix_entitlements_updated_by');
            $table->comment('Danh mục quyền lợi');
        });

        Schema::create('plans', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->smallIncrements('id');
            $table->string('code', 40)->charset('ascii')->collation('ascii_bin')->comment('free, premium_1m, premium_12m, lifetime, seat_12m');
            $table->string('name', 120);
            $table->string('description', 500)->nullable();
            $table->unsignedTinyInteger('tier')->comment('1 Free, 2 Premium; khớp access_tier của nội dung');
            $table->unsignedBigInteger('price')->default(0)->comment('đồng');
            $table->unsignedBigInteger('compare_at_price')->nullable()->comment('giá gạch');
            $table->char('currency', 3)->default('VND')->charset('ascii')->collation('ascii_bin');
            $table->string('period_unit', 10)->charset('ascii')->collation('ascii_bin')->comment('none, day, month, year, lifetime');
            $table->unsignedSmallInteger('period_count')->default(1);
            $table->unsignedSmallInteger('trial_days')->default(0);
            $table->boolean('is_b2b')->default(0);
            $table->boolean('is_public')->default(1);
            $table->boolean('is_active')->default(1);
            $table->smallInteger('sort')->default(0);
            $table->unsignedInteger('created_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedInteger('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedInteger('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->index(['created_by'], 'ix_plans_created_by');
            $table->index(['deleted_by'], 'ix_plans_deleted_by');
            $table->index(['updated_by'], 'ix_plans_updated_by');
            $table->unique(['code'], 'uq_plans_code');
            $table->comment('Gói bán');
        });

        Schema::create('plan_entitlements', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->unsignedSmallInteger('plan_id');
            $table->string('entitlement_code', 40)->charset('ascii')->collation('ascii_bin');
            $table->json('value');
            $table->unsignedInteger('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->primary(['plan_id', 'entitlement_code']);
            $table->index(['entitlement_code'], 'ix_plan_ent_code');
            $table->index(['updated_by'], 'ix_plan_ent_updated_by');
            $table->comment('Quyền lợi của từng gói');
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->unsignedSmallInteger('plan_id');
            $table->string('status', 20)->charset('ascii')->collation('ascii_bin')->comment('trialing, active, expired, canceled, revoked');
            $table->string('source', 20)->charset('ascii')->collation('ascii_bin')->comment('order, trial, admin, coupon, referral, organization');
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedInteger('starts_at');
            $table->unsignedInteger('ends_at')->nullable()->comment('NULL = trọn đời');
            $table->unsignedInteger('canceled_at')->nullable();
            $table->string('note', 255)->nullable();
            $table->unsignedInteger('created_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->comment('nhân viên cấp tay, nếu có');
            $table->unsignedInteger('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->index(['created_by'], 'ix_subs_created_by');
            $table->index(['status', 'ends_at'], 'ix_subs_ends');
            $table->index(['order_id'], 'ix_subs_order');
            $table->index(['organization_id'], 'ix_subs_org');
            $table->index(['plan_id'], 'ix_subs_plan');
            $table->index(['updated_by'], 'ix_subs_updated_by');
            $table->index(['user_id', 'status', 'ends_at'], 'ix_subs_user');
            $table->comment('Thời hạn dùng gói; mua thêm thì nối tiếp');
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->char('public_id', 26)->charset('ascii')->collation('ascii_bin');
            $table->string('code', 20)->charset('ascii')->collation('ascii_bin')->comment('ghi vào nội dung chuyển khoản, vd LB2611250042');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->unsignedSmallInteger('plan_id');
            $table->unsignedSmallInteger('quantity')->default(1)->comment('số chỗ học (B2B)');
            $table->unsignedBigInteger('subtotal');
            $table->unsignedBigInteger('discount')->default(0);
            $table->unsignedBigInteger('total');
            $table->char('currency', 3)->default('VND')->charset('ascii')->collation('ascii_bin');
            $table->unsignedInteger('coupon_id')->nullable();
            $table->string('status', 20)->default('pending')->charset('ascii')->collation('ascii_bin')->comment('pending, paid, expired, canceled, refunded, partially_refunded');
            $table->string('payment_method', 20)->nullable()->charset('ascii')->collation('ascii_bin')->comment('bank_qr, momo, zalopay, paypal, manual');
            $table->unsignedInteger('expires_at')->nullable();
            $table->unsignedInteger('paid_at')->nullable();
            $table->boolean('invoice_requested')->default(0);
            $table->json('billing_info')->nullable()->comment('tên, mã số thuế, địa chỉ, email nhận hoá đơn');
            $table->json('meta')->nullable()->comment('utm, thiết bị');
            $table->unsignedInteger('created_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedInteger('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->index(['coupon_id'], 'ix_orders_coupon');
            $table->index(['created_by'], 'ix_orders_created_by');
            $table->index(['organization_id'], 'ix_orders_org');
            $table->index(['plan_id'], 'ix_orders_plan');
            $table->index(['status', 'expires_at'], 'ix_orders_status');
            $table->index(['updated_by'], 'ix_orders_updated_by');
            $table->index(['user_id', 'created_at'], 'ix_orders_user');
            $table->unique(['code'], 'uq_orders_code');
            $table->unique(['public_id'], 'uq_orders_public');
            $table->comment('Đơn mua gói');
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('order_id')->nullable()->comment('NULL = tiền vào chưa khớp đơn');
            $table->string('gateway', 20)->charset('ascii')->collation('ascii_bin')->comment('sepay, payos, momo, zalopay, paypal, manual');
            $table->string('gateway_txn_id', 100)->charset('ascii')->collation('ascii_bin');
            $table->unsignedBigInteger('amount');
            $table->char('currency', 3)->default('VND')->charset('ascii')->collation('ascii_bin');
            $table->string('status', 20)->charset('ascii')->collation('ascii_bin')->comment('succeeded, unmatched, failed, refunded');
            $table->string('transfer_content', 255)->nullable()->comment('nội dung chuyển khoản');
            $table->json('payer_info')->nullable();
            $table->json('raw')->nullable();
            $table->unsignedInteger('paid_at')->nullable();
            $table->unsignedBigInteger('matched_by')->nullable()->comment('nhân viên khớp tay');
            $table->unsignedInteger('created_at')->nullable();
            $table->unsignedInteger('updated_at')->nullable();
            $table->index(['matched_by'], 'ix_payments_matched_by');
            $table->index(['order_id'], 'ix_payments_order');
            $table->index(['status', 'created_at'], 'ix_payments_status');
            $table->unique(['gateway', 'gateway_txn_id'], 'uq_payments_txn');
            $table->comment('Giao dịch tiền vào, mỗi giao dịch ghi một lần');
        });

        Schema::create('payment_events', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->string('gateway', 20)->charset('ascii')->collation('ascii_bin');
            $table->string('event_key', 120)->charset('ascii')->collation('ascii_bin')->comment('id sự kiện của cổng hoặc sha256 của payload');
            $table->boolean('signature_ok')->default(0);
            $table->json('payload');
            $table->string('status', 20)->default('received')->charset('ascii')->collation('ascii_bin')->comment('received, processed, ignored, failed');
            $table->string('error', 500)->nullable();
            $table->unsignedInteger('received_at');
            $table->unsignedInteger('processed_at')->nullable();
            $table->index(['status', 'received_at'], 'ix_payment_events_status');
            $table->unique(['gateway', 'event_key'], 'uq_payment_events');
            $table->comment('Webhook nhận từ cổng thanh toán, chống xử lý trùng');
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->unsignedBigInteger('amount');
            $table->string('reason', 500)->nullable();
            $table->string('status', 20)->default('pending')->charset('ascii')->collation('ascii_bin')->comment('pending, done, failed');
            $table->unsignedInteger('processed_at')->nullable();
            $table->unsignedInteger('created_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedInteger('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->index(['created_by'], 'ix_refunds_created_by');
            $table->index(['order_id'], 'ix_refunds_order');
            $table->index(['payment_id'], 'ix_refunds_payment');
            $table->index(['updated_by'], 'ix_refunds_updated_by');
            $table->comment('Hoàn tiền');
        });

        Schema::create('coupons', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->increments('id');
            $table->string('code', 40)->charset('ascii')->collation('ascii_bin')->comment('lưu viết hoa');
            $table->string('kind', 10)->charset('ascii')->collation('ascii_bin')->comment('percent, fixed, days');
            $table->unsignedInteger('value');
            $table->json('plan_ids')->nullable()->comment('NULL = mọi gói');
            $table->unsignedInteger('max_redemptions')->nullable();
            $table->unsignedSmallInteger('per_user_limit')->default(1);
            $table->unsignedInteger('redeemed_count')->default(0);
            $table->unsignedInteger('starts_at')->nullable();
            $table->unsignedInteger('ends_at')->nullable();
            $table->boolean('is_active')->default(1);
            $table->unsignedInteger('created_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedInteger('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedInteger('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->index(['created_by'], 'ix_coupons_created_by');
            $table->index(['deleted_by'], 'ix_coupons_deleted_by');
            $table->index(['updated_by'], 'ix_coupons_updated_by');
            $table->unique(['code'], 'uq_coupons_code');
            $table->comment('Mã giảm giá');
        });

        Schema::create('coupon_redemptions', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->unsignedInteger('coupon_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedInteger('created_at');
            $table->index(['coupon_id', 'user_id'], 'ix_coupon_red');
            $table->index(['order_id'], 'ix_coupon_red_order');
            $table->index(['user_id'], 'ix_coupon_red_user');
            $table->comment('Lượt dùng mã giảm giá');
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('order_id');
            $table->string('number', 40)->nullable()->comment('số hoá đơn điện tử');
            $table->json('buyer');
            $table->unsignedBigInteger('amount_before_tax');
            $table->decimal('tax_rate', 5, 2)->nullable();
            $table->unsignedBigInteger('tax_amount')->default(0);
            $table->unsignedBigInteger('total');
            $table->string('status', 20)->default('pending')->charset('ascii')->collation('ascii_bin')->comment('pending, issued, failed, canceled');
            $table->string('provider', 30)->nullable()->charset('ascii')->collation('ascii_bin');
            $table->string('provider_ref', 100)->nullable();
            $table->string('pdf_path', 255)->nullable();
            $table->unsignedInteger('issued_at')->nullable();
            $table->unsignedInteger('created_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedInteger('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->index(['created_by'], 'ix_invoices_created_by');
            $table->index(['order_id'], 'ix_invoices_order');
            $table->index(['status'], 'ix_invoices_status');
            $table->index(['updated_by'], 'ix_invoices_updated_by');
            $table->comment('Hoá đơn điện tử');
        });

        Schema::create('referrals', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('referrer_id');
            $table->unsignedBigInteger('referred_id');
            $table->string('status', 20)->default('signed_up')->charset('ascii')->collation('ascii_bin')->comment('signed_up, converted, rewarded, rejected');
            $table->unsignedSmallInteger('reward_days')->default(0);
            $table->unsignedInteger('rewarded_at')->nullable();
            $table->unsignedInteger('created_at');
            $table->index(['referrer_id'], 'ix_referrals_referrer');
            $table->unique(['referred_id'], 'uq_referrals_referred');
            $table->comment('Giới thiệu bạn bè');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referrals');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('coupon_redemptions');
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('payment_events');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plan_entitlements');
        Schema::dropIfExists('plans');
        Schema::dropIfExists('entitlements');
    }
};
