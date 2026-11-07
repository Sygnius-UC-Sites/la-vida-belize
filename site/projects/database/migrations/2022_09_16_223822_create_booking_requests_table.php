<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('booking_requests', function (Blueprint $table) {
            $table->id();
            $table->integer("property_id");
            $table->date("checkin");
            $table->date("checkout");
            $table->integer("total_guests")->nullable();
            $table->integer("adults")->nullable();
            $table->integer("child")->nullable();
            $table->double("gross_amount")->nullable();
            $table->double("total_night")->nullable();
            $table->double("sub_amount")->nullable();
            $table->double("total_amount")->nullable();
            $table->longText("after_total_fees")->nullable();
            $table->longText("before_total_fees")->nullable();
            $table->string("request_id")->nullable();
            
            $table->string("booking_status")->default("booked");
            
            $table->enum("email_status",["true","false"])->default('false');

            $table->enum("payment_status",["paid","partially","pending","declined","failed","check-booking"])->default("pending");

            $table->enum("welcome_email",["true","false"])->default('false');
            $table->enum("review_email",["true","false"])->default('false');
            $table->enum("reminder_email",["true","false"])->default('false');
            $table->enum("third_reminder_email",["true","false"])->default('false');
            $table->enum("checkin_email",["true","false"])->default('false');
            $table->enum("checkout_email",["true","false"])->default('false');




            $table->string("name");
            $table->string("email");
            $table->string("mobile")->nullable();
            $table->longText("message")->nullable();



            $table->string("ip_address")->nullable();


            $table->longText("cancel_reason")->nullable();
            $table->longText("note")->nullable();
            $table->enum('rental_aggrement_status', ['true', 'false'])->default('false');
            $table->text('rental_aggrement_signature')->nullable();
            $table->longText('rental_aggrement_images')->nullable();
            $table->text('rental_agreement_link')->nullable();
            $table->integer('total_payment')->default(1);
            $table->mediumText('amount_data')->nullable();
            $table->integer('how_many_payment_done')->default(0);
            $table->string('total_pets', 50)->nullable();
            $table->string('pet_fee', 50)->nullable();
            $table->string('guest_fee', 50)->nullable();
            $table->string('rest_guests', 50)->nullable();
            $table->string('single_guest_fee', 50)->nullable();
            $table->string('discount', 50)->nullable();
            $table->string('discount_coupon', 50)->nullable();
            $table->string('after_discount_total', 50)->nullable();
            $table->integer('extra_discount')->default(0);
            $table->string('color', 50)->default('red');
            $table->double('heating_pool_fee')->nullable();
            $table->string('tax', 155)->nullable();
            $table->string('define_tax', 155)->nullable();
            $table->string('pet_fee_type', 191)->default('taxable');
            $table->string('heating_pool_fee_type', 191)->default('taxable');
            $table->string('booking_type_admin')->default('invoice');
            $table->timestamp('quote_date')->nullable();
            $table->string('quote_ip', 100)->nullable();
            $table->timestamp('rentar_agreement_date')->nullable();
            $table->string('rentar_agreement_ip', 100)->nullable();
            $table->longText('custom_before_total_fees')->nullable();
            $table->string('heating_pool_fee_data_guarav', 20)->default('no');
            $table->string('extra_amount')->nullable();
            $table->string('operation')->nullable();
            $table->text('customer_profile_id')->nullable();
            $table->text('customer_profile_payment_id')->nullable();
            $table->text('customer_json_data')->nullable();
            $table->text('payment_method')->nullable();
            $table->text('security_deposit_data')->nullable();
            $table->integer('card_need_hide')->nullable();
            $table->date('cancel_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('booking_requests');
    }
};
