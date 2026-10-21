<?php

namespace App\Services;

use App\Enums\UserType;
use App\Models\Business;
use App\Support\InstanceContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use App\Enums\Status;

class BusinessService
{
    public const RELATIONS = [
        'client.phones',
        'user',
        'category',
        'product',
        'funnel',
        'stage',
        'events.user',
        'messages',
    ];

    public function paginate(Request $request): LengthAwarePaginator
    {
        $user = $request->user();

        $query = Business::query()
            ->where('instance_id', InstanceContext::id($request))
            ->with(self::RELATIONS)
            ->when($request->filled('stage_id'), fn (Builder $query) => $query->where('stage_id', $request->integer('stage_id')))
            ->when($request->boolean('expired'), function (Builder $query): void {
                $query->where('expiration_date', '<', now());
            }, function (Builder $query) use ($request): void {
                $query->where('status', $request->query('status', Status::Open->number()));
            });

       $search = $request->query('search');

        if (! empty($search)) {
            $search = strtolower((string) $search);
            $registrationSearch = preg_replace('/\D+/', '', $search);
        
            $query->whereHas('client', function (Builder $clientQuery) use ($search, $registrationSearch): void {
                $clientQuery->whereRaw('LOWER(fullname) LIKE ?', ['%' . $search . '%']);
        
                if ($registrationSearch !== '') {
                    $clientQuery->orWhere('registration', 'like', '%' . $registrationSearch . '%');
                }
            });
        }

        if (! in_array($user->type, [UserType::Master, UserType::Admin], true)) {
            $query->where('user_id', $user->id);
        }

        return $query->latest()->paginate();
    }
}
