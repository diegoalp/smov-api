<?php

namespace App\Services;

use App\Models\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ClientResolver
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function resolve(array $data, int $instanceId): Client
    {
        $registration = preg_replace('/\D+/', '', (string) ($data['registration'] ?? ''));
        $type = (string) ($data['type'] ?? 'individual');

        Validator::make(['registration' => $registration], [
            'registration' => [$type === 'individual' ? 'size:11' : 'size:14'],
        ])->validate();

        return DB::transaction(function () use ($data, $instanceId, $registration, $type): Client {
            $client = Client::firstOrCreate([
                'instance_id' => $instanceId,
                'registration' => $registration,
            ], [
                'fullname' => $data['fullname'],
                'type' => $type,
                'birthdate' => $data['birthdate'] ?? null,
            ]);

            $client->update(array_filter([
                'fullname' => $data['fullname'],
                'type' => $type,
                'birthdate' => $data['birthdate'] ?? null,
                'extra' => $data['extra'] ?? null,
            ], fn ($value) => $value !== null));

            foreach ($data['phones'] ?? [] as $phone) {
                $number = preg_replace('/\D+/', '', (string) $phone['number']);
                $client->phones()->updateOrCreate(['number' => $number], ['whatsapp' => $phone['whatsapp'] ?? false]);
            }

            return $client->load('phones');
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    public function businessClientPayload(array $payload): ?array
    {
        $data = $payload['client'] ?? $payload['client_data'] ?? null;

        if ($data === null) {
            return null;
        }

        if (! is_array($data)) {
            throw ValidationException::withMessages(['client' => 'Os dados do cliente devem ser um objeto.']);
        }

        Validator::make($data, [
            'fullname' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:individual,company'],
            'registration' => ['required', 'string', 'max:18'],
            'birthdate' => ['nullable', 'date', 'before:today'],
            'phones' => ['sometimes', 'array'],
            'phones.*.number' => ['required', 'string', 'max:20'],
            'phones.*.whatsapp' => ['sometimes', 'boolean'],
            'extra' => ['sometimes', 'nullable', 'array'],
        ])->validate();

        return $data;
    }
}
