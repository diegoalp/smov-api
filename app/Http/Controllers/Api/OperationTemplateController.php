<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InstalledOperationTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Support\InstanceContext;

class OperationTemplateController extends Controller
{
    private const KEYS = ['credito-consignado', 'imobiliaria', 'clinica-estetica', 'b2b-saas'];

    public function show(Request $request): JsonResponse
    {
        $this->requireInstance($request);

        return response()->json(['data' => InstalledOperationTemplate::where('instance_id', InstanceContext::id($request))->first()]);
    }

    public function install(Request $request): JsonResponse
    {
        $instanceId = $this->requireInstance($request);
        $data = $request->validate(['templateId' => ['required', Rule::in(self::KEYS)]]);
        $installed = InstalledOperationTemplate::updateOrCreate(
            ['instance_id' => $instanceId],
            ['template_key' => $data['templateId'], 'installed_at' => now(), 'installed_by' => $request->user()->id],
        );

        return response()->json(['data' => ['templateId' => $installed->template_key, 'installedAt' => $installed->installed_at]]);
    }

    private function requireInstance(Request $request): int
    {
        return InstanceContext::id($request);
    }
}
