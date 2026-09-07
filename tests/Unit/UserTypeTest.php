<?php

namespace Tests\Unit;

use App\Enums\UserType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UserTypeTest extends TestCase
{
    #[DataProvider('userTypes')]
    public function test_it_has_the_expected_value_and_label(
        UserType $type,
        string $value,
        string $label,
    ): void {
        $this->assertSame($value, $type->value);
        $this->assertSame($label, $type->label());
    }

    /**
     * @return array<string, array{UserType, string, string}>
     */
    public static function userTypes(): array
    {
        return [
            'master' => [UserType::Master, 'master', 'Master'],
            'admin' => [UserType::Admin, 'admin', 'Administrador'],
            'seller' => [UserType::Seller, 'seller', 'Vendedor'],
        ];
    }
}
