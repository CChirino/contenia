<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\BrandContact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandContactTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_adds_and_deletes_contacts(): void
    {
        $brand = Brand::firstOrFail();
        $this->actingAsAdminFor($brand);

        $this->post(route('brands.contacts.store', $brand), [
            'name' => 'Ana Cliente',
            'email' => 'ana@cliente.test',
            'whatsapp' => '+584120000000',
        ])->assertRedirect();

        $contact = BrandContact::firstOrFail();
        $this->assertSame($brand->id, $contact->brand_id);
        $this->assertSame('ana@cliente.test', $contact->email);

        $this->delete(route('brands.contacts.destroy', [$brand->id, $contact->id]))
            ->assertRedirect();
        $this->assertSame(0, BrandContact::count());
    }

    public function test_operator_cannot_manage_contacts(): void
    {
        $brand = Brand::firstOrFail();
        $this->actingAsOperatorFor($brand);

        $this->post(route('brands.contacts.store', $brand), [
            'name' => 'X', 'email' => 'x@x.test',
        ])->assertForbidden();
    }

    public function test_email_must_be_unique_per_brand(): void
    {
        $brand = Brand::firstOrFail();
        $this->actingAsAdminFor($brand);

        $this->post(route('brands.contacts.store', $brand), [
            'name' => 'A', 'email' => 'dup@test.com',
        ])->assertRedirect();

        $this->post(route('brands.contacts.store', $brand), [
            'name' => 'B', 'email' => 'dup@test.com',
        ])->assertSessionHasErrors('email');

        // El mismo email en otra marca sí vale.
        $other = Brand::factory()->create();
        $this->post(route('brands.contacts.store', $other), [
            'name' => 'C', 'email' => 'dup@test.com',
        ])->assertRedirect();

        // BrandScope: contar sin filtro de marca (el test admin tiene una
        // marca activa que limita las consultas).
        $this->assertSame(2, BrandContact::withoutGlobalScopes()->count());
    }

    public function test_contact_of_other_brand_returns_404(): void
    {
        $brand = Brand::firstOrFail();
        $other = Brand::factory()->create();
        $contact = BrandContact::factory()->create(['brand_id' => $other->id]);
        $this->actingAsAdminFor($brand);

        $this->delete(route('brands.contacts.destroy', [$brand->id, $contact->id]))
            ->assertNotFound();
        $this->assertSame(1, BrandContact::withoutGlobalScopes()->count());
    }
}
