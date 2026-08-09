<?php

namespace Tests\Unit;

use App\Http\Controllers\AbsensiController;
use App\Models\Siswa;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class ResolveParentContactsTest extends TestCase
{
    private function resolve(Siswa $siswa): array
    {
        $method = new ReflectionMethod(AbsensiController::class, 'resolveParentContacts');
        $method->setAccessible(true);

        return $method->invoke(new AbsensiController, $siswa);
    }

    private function siswa(array $attributes = []): Siswa
    {
        return new Siswa(array_replace([
            'no_whatsapp_ayah' => null,
            'no_whatsapp_ibu' => null,
        ], $attributes));
    }

    public function test_it_collects_each_unique_parent_number_without_duplicates(): void
    {
        $contacts = $this->resolve($this->siswa([
            'nama_ayah' => 'Ayah',
            'no_whatsapp_ayah' => '081234567890',
            'nama_ibu' => 'Ibu',
            'no_whatsapp_ibu' => '081234567890',
        ]));

        $this->assertSame([['Ayah', '6281234567890']], $contacts);
    }

    public function test_it_normalizes_local_to_international_format(): void
    {
        $contacts = $this->resolve($this->siswa([
            'nama_ayah' => 'Ayah',
            'no_whatsapp_ayah' => '081234567890',
            'nama_ibu' => 'Ibu',
            'no_whatsapp_ibu' => '81298765432',
        ]));

        $this->assertSame([
            ['Ayah', '6281234567890'],
            ['Ibu', '6281298765432'],
        ], $contacts);
    }

    public function test_it_empties_when_no_contact_is_available(): void
    {
        $this->assertSame([], $this->resolve($this->siswa()));
    }

    public function test_priority_is_ayah_then_ibu(): void
    {
        $contacts = $this->resolve($this->siswa([
            'nama_ayah' => 'Ayah',
            'no_whatsapp_ayah' => '08122222222',
            'nama_ibu' => 'Ibu',
            'no_whatsapp_ibu' => '08133333333',
        ]));

        $this->assertSame([
            ['Ayah', '628122222222'],
            ['Ibu', '628133333333'],
        ], $contacts);
    }
}
