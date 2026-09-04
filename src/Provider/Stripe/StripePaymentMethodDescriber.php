<?php

namespace Pantono\Payments\Provider\Stripe;

/**
 * Builds a human readable description of the payment method used for a charge,
 * from the payment_method_details of a Stripe charge.
 */
class StripePaymentMethodDescriber
{
    private const LABELS = [
        'acss_debit' => 'Pre-authorised debit',
        'affirm' => 'Affirm',
        'afterpay_clearpay' => 'Clearpay',
        'alipay' => 'Alipay',
        'alma' => 'Alma',
        'amazon_pay' => 'Amazon Pay',
        'au_becs_debit' => 'BECS Direct Debit',
        'bacs_debit' => 'Bacs Direct Debit',
        'bancontact' => 'Bancontact',
        'billie' => 'Billie',
        'bizum' => 'Bizum',
        'blik' => 'BLIK',
        'boleto' => 'Boleto',
        'card' => 'Card',
        'card_present' => 'Card',
        'cashapp' => 'Cash App Pay',
        'crypto' => 'Crypto',
        'customer_balance' => 'Bank transfer',
        'eps' => 'EPS',
        'fpx' => 'FPX',
        'giropay' => 'giropay',
        'grabpay' => 'GrabPay',
        'ideal' => 'iDEAL',
        'interac_present' => 'Interac',
        'kakao_pay' => 'Kakao Pay',
        'klarna' => 'Klarna',
        'konbini' => 'Konbini',
        'kr_card' => 'Card',
        'link' => 'Link',
        'mb_way' => 'MB WAY',
        'mobilepay' => 'MobilePay',
        'multibanco' => 'Multibanco',
        'naver_pay' => 'Naver Pay',
        'nz_bank_account' => 'Bank account',
        'oxxo' => 'OXXO',
        'p24' => 'Przelewy24',
        'pay_by_bank' => 'Pay by Bank',
        'payco' => 'PAYCO',
        'paynow' => 'PayNow',
        'paypal' => 'PayPal',
        'payto' => 'PayTo',
        'pix' => 'Pix',
        'promptpay' => 'PromptPay',
        'revolut_pay' => 'Revolut Pay',
        'samsung_pay' => 'Samsung Pay',
        'satispay' => 'Satispay',
        'scalapay' => 'Scalapay',
        'sepa_credit_transfer' => 'SEPA Credit Transfer',
        'sepa_debit' => 'SEPA Direct Debit',
        'sofort' => 'SOFORT',
        'sunbit' => 'Sunbit',
        'swish' => 'Swish',
        'twint' => 'TWINT',
        'upi' => 'UPI',
        'us_bank_account' => 'Bank account',
        'wechat' => 'WeChat',
        'wechat_pay' => 'WeChat Pay',
        'zip' => 'Zip',
    ];

    /**
     * @param array $details The payment_method_details of a Stripe charge
     */
    public static function describe(array $details): ?string
    {
        $type = $details['type'] ?? null;
        if (!is_string($type) || $type === '') {
            return null;
        }
        $data = $details[$type] ?? [];
        if (!is_array($data)) {
            $data = [];
        }

        $label = self::label($type);
        if ($type === 'card' || $type === 'card_present' || $type === 'kr_card') {
            return self::describeCard($data, $label);
        }

        $detail = self::describeDetail($data);

        return $detail !== null ? $label . ' ' . $detail : $label;
    }

    private static function label(string $type): string
    {
        return self::LABELS[$type] ?? ucwords(str_replace('_', ' ', $type));
    }

    /**
     * Cards keep their existing "Visa ending 4242" wording. A card presented
     * through a digital wallet leads with the wallet, as that is what the
     * customer will recognise having paid with.
     */
    private static function describeCard(array $card, string $label): string
    {
        $brand = self::getString($card, 'display_brand') ?? self::getString($card, 'brand');
        $last4 = self::getString($card, 'last4');

        $description = $brand ?? $label;
        if ($last4 !== null) {
            $description .= ' ending ' . $last4;
        }

        $wallet = $card['wallet'] ?? null;
        $walletType = is_array($wallet) ? self::getString($wallet, 'type') : null;
        if ($walletType !== null) {
            if ($brand === null && $last4 === null) {
                return self::label($walletType);
            }

            return self::label($walletType) . ' (' . $description . ')';
        }

        return $description;
    }

    /**
     * The identifying detail Stripe gives us varies by method, so take the most
     * specific one available rather than branching per payment method type.
     */
    private static function describeDetail(array $data): ?string
    {
        foreach (['last4', 'iban_last4'] as $key) {
            $value = self::getString($data, $key);
            if ($value !== null) {
                return 'ending ' . $value;
            }
        }

        $fundingCard = self::describeFundingCard($data);
        if ($fundingCard !== null) {
            return '(' . $fundingCard . ')';
        }

        foreach (['payer_email', 'bank_name', 'verified_name'] as $key) {
            $value = self::getString($data, $key);
            if ($value !== null) {
                return '(' . $value . ')';
            }
        }

        return null;
    }

    /**
     * Wallets such as Amazon Pay and Revolut Pay report the card they were
     * funded by under funding, MobilePay reports it directly.
     */
    private static function describeFundingCard(array $data): ?string
    {
        $funding = $data['funding'] ?? null;
        $card = is_array($funding) ? ($funding['card'] ?? null) : ($data['card'] ?? null);
        if (!is_array($card)) {
            return null;
        }

        $brand = self::getString($card, 'display_brand') ?? self::getString($card, 'brand');
        $last4 = self::getString($card, 'last4');
        if ($brand === null) {
            return $last4 !== null ? 'ending ' . $last4 : null;
        }

        return $last4 !== null ? $brand . ' ending ' . $last4 : $brand;
    }

    private static function getString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
