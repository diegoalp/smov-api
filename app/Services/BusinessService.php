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
    public const RELATIONS = ['client.phones', 'user', 'category', 'product', 'funnel', 'stage', 'events.user', 'messages'];

    public function paginate(Request $request): LengthAwarePaginator
    {
        $user = $request->user();

        $query = Business::query()
            ->where('instance_id', InstanceContext::id($request))
            ->where('stage_id', $request->integer('stage_id'))
            ->with(self::RELATIONS)
            ->where('status', $request->query('status',Status::Open->number())); //Se for passado o status na query, filtra pelo status, senão filtra pelo status aberto

        if ($request->filled('client_name')) {
            $query->whereHas('client', function (Builder $clientQuery) use ($request): void {
                $clientQuery->where('fullname', 'like', '%'.$request->query('client_name').'%');
            });
        }

        if (! in_array($user->type, [UserType::Master, UserType::Admin], true)) {
            $query->where('user_id', $user->id);
        }

        return $query->latest()->paginate();
    }
}
