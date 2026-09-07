<?php

namespace Database\Seeders;

use App\Enums\UserType;
use App\Models\Business;
use App\Models\Category;
use App\Models\Client;
use App\Models\Disposition;
use App\Models\Funnel;
use App\Models\Instance;
use App\Models\LeadSource;
use App\Models\Product;
use App\Models\Stage;
use App\Models\User;
use Illuminate\Database\Seeder;

class ConsignedLoanCrmSeeder extends Seeder
{
    public function run(): void
    {
        $instance = Instance::create([
            'name' => 'Consignado Cred',
            'primary_color' => '#1D4ED8',
            'secondary_color' => '#0F172A',
            'accent_color' => '#10B981',
            'primary_text_color' => '#FFFFFF',
        ]);

        $funnel = Funnel::create([
            'instance_id' => $instance->id,
            'name' => 'Empréstimo Consignado',
            'description' => 'Jornada comercial da captação à liberação do crédito.',
            'owner_team' => 'Equipe Comercial',
            'color' => '#2563EB',
            'active' => true,
        ]);

        $stages = collect([
            ['name' => 'Novo lead', 'duration' => 4, 'duration_unit' => 'horas', 'color' => '#3B82F6'],
            ['name' => 'Em contato', 'duration' => 1, 'duration_unit' => 'dias', 'color' => '#8B5CF6'],
            ['name' => 'Documentação', 'duration' => 2, 'duration_unit' => 'dias', 'color' => '#F59E0B'],
            ['name' => 'Simulação enviada', 'duration' => 1, 'duration_unit' => 'dias', 'color' => '#F97316'],
            ['name' => 'Aguardando averbação', 'duration' => 3, 'duration_unit' => 'dias', 'color' => '#06B6D4'],
            ['name' => 'Crédito liberado', 'duration' => null, 'duration_unit' => null, 'color' => '#10B981', 'is_final' => true],
        ])->map(fn (array $stage, int $position) => Stage::create([
            'funnel_id' => $funnel->id,
            'position' => $position + 1,
            'is_final' => false,
            ...$stage,
        ]));

        $categories = collect([
            ['name' => 'INSS', 'description' => 'Aposentados e pensionistas do INSS.'],
            ['name' => 'Servidor público', 'description' => 'Servidores municipais, estaduais e federais.'],
            ['name' => 'Forças Armadas', 'description' => 'Militares ativos, inativos e pensionistas.'],
        ])->map(fn (array $data) => Category::create(['instance_id' => $instance->id, 'active' => true, ...$data]));

        $products = collect([
            ['title' => 'Novo empréstimo', 'prefix' => 'NOVO', 'color' => '#2563EB', 'description' => 'Nova contratação de crédito consignado.'],
            ['title' => 'Refinanciamento', 'prefix' => 'REFIN', 'color' => '#7C3AED', 'description' => 'Renovação de contrato com liberação de troco.'],
            ['title' => 'Portabilidade', 'prefix' => 'PORT', 'color' => '#059669', 'description' => 'Transferência de dívida para uma condição mais vantajosa.'],
            ['title' => 'Cartão consignado', 'prefix' => 'CARTAO', 'color' => '#EA580C', 'description' => 'Cartão com desconto mínimo em folha.'],
        ])->map(fn (array $data) => Product::create(['instance_id' => $instance->id, 'active' => true, ...$data]));

        $funnel->categories()->sync($categories->pluck('id'));
        $funnel->products()->sync($products->pluck('id'));
        foreach ($categories as $category) {
            $category->products()->sync($products->pluck('id'));
        }

        $sources = collect([
            ['name' => 'Indicação', 'type' => 'manual', 'description' => 'Cliente indicado pela carteira.'],
            ['name' => 'Landing page', 'type' => 'digital', 'description' => 'Lead captado pelo formulário do site.'],
            ['name' => 'WhatsApp', 'type' => 'digital', 'description' => 'Contato iniciado pelo WhatsApp.'],
            ['name' => 'Base de clientes', 'type' => 'campaign', 'description' => 'Oferta para clientes da base.'],
        ])->map(fn (array $data) => LeadSource::create(['instance_id' => $instance->id, 'active' => true, ...$data]));

        foreach (['Sem margem', 'Benefício bloqueado', 'Taxa recusada', 'Documentação incompleta', 'Cliente desistiu'] as $name) {
            Disposition::create(['instance_id' => $instance->id, 'name' => $name]);
        }

        $seller = User::create([
            'instance_id' => $instance->id,
            'funnel_id' => $funnel->id,
            'name' => 'Ana',
            'lastname' => 'Consultora',
            'email' => 'ana@consignadocred.local',
            'password' => 'password',
            'type' => UserType::Seller,
        ]);

        $clients = collect([
            ['fullname' => 'Maria Aparecida Souza', 'registration' => '12345678901', 'phone' => '11999990001'],
            ['fullname' => 'João Carlos Oliveira', 'registration' => '12345678902', 'phone' => '11999990002'],
            ['fullname' => 'Associação Beneficente Exemplo', 'registration' => '12345678000190', 'phone' => '1133334444', 'type' => 'company'],
        ])->map(function (array $data) {
            $client = Client::create([
                'fullname' => $data['fullname'],
                'type' => $data['type'] ?? 'individual',
                'registration' => $data['registration'],
            ]);
            $client->phones()->create(['number' => $data['phone'], 'whatsapp' => true]);

            return $client;
        });

        foreach ($clients as $index => $client) {
            Business::create([
                'instance_id' => $instance->id,
                'client_id' => $client->id,
                'user_id' => $seller->id,
                'category_id' => $categories[$index % $categories->count()]->id,
                'product_id' => $products[$index % $products->count()]->id,
                'funnel_id' => $funnel->id,
                'stage_id' => $stages[$index]->id,
                'lead_source_id' => $sources[$index % $sources->count()]->id,
                'status' => 1,
                'value' => [18500, 9200, 35000][$index],
                'notes' => 'Registro demonstrativo criado pelo seeder de consignado.',
            ]);
        }
    }
}
