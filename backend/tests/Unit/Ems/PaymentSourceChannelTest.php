<?php

namespace Tests\Unit\Ems;

use App\Ems\Enums\PaymentSourceChannel;
use PHPUnit\Framework\TestCase;

class PaymentSourceChannelTest extends TestCase
{
    public function test_maps_square_products_without_changing_existing_semantics(): void
    {
        $this->assertSame(
            PaymentSourceChannel::Terminal,
            PaymentSourceChannel::fromSquarePayment(['terminal_checkout_id' => 'TERM_1'])
        );
        $this->assertSame(
            PaymentSourceChannel::Terminal,
            PaymentSourceChannel::fromSquarePayment(['application_details' => ['square_product' => 'TERMINAL_API']])
        );
        $this->assertSame(
            PaymentSourceChannel::Pos,
            PaymentSourceChannel::fromSquarePayment(['application_details' => ['square_product' => 'SQUARE_POS']])
        );
        $this->assertSame(
            PaymentSourceChannel::Pos,
            PaymentSourceChannel::fromSquarePayment(['application_details' => ['square_product' => 'VIRTUAL_TERMINAL']])
        );
        $this->assertSame(
            PaymentSourceChannel::Online,
            PaymentSourceChannel::fromSquarePayment(['application_details' => ['square_product' => 'ECOMMERCE_API']])
        );
        $this->assertSame(
            PaymentSourceChannel::Online,
            PaymentSourceChannel::fromSquarePayment(['application_details' => ['square_product' => 'INVOICES']])
        );
        $this->assertSame(
            PaymentSourceChannel::SquareOnlineStore,
            PaymentSourceChannel::fromSquarePayment(['application_details' => ['square_product' => 'ONLINE_STORE']])
        );
        $this->assertSame(
            PaymentSourceChannel::Other,
            PaymentSourceChannel::fromSquarePayment(['application_details' => ['square_product' => 'RETAIL']])
        );
    }

    public function test_online_store_is_not_walk_in_and_does_not_treat_approved_as_settled(): void
    {
        $channel = PaymentSourceChannel::SquareOnlineStore;

        $this->assertSame('square_online_store', $channel->value);
        $this->assertSame('Square Online Store', $channel->label());
        $this->assertSame('square_online_store', $channel->registrationSource());
        $this->assertFalse($channel->isWalkIn());
        $this->assertFalse($channel->treatsApprovedAsSettled());
        $this->assertTrue(PaymentSourceChannel::Pos->treatsApprovedAsSettled());
        $this->assertTrue(PaymentSourceChannel::Pos->isWalkIn());
    }
}
