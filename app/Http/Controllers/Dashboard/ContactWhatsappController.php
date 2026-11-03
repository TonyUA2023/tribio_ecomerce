<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\Crm\ContactService;
use App\Services\Crm\CrmSchema;
use App\Services\Crm\MessageTemplates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Opens WhatsApp with a template for one customer and writes it in their timeline.
 * GET /dashboard/clientes/{contact}/whatsapp?plantilla=reorder&producto=12
 */
class ContactWhatsappController extends Controller
{
    public function open(Request $request, CrmSchema $schema, MessageTemplates $templates, ContactService $contacts, int $contact): RedirectResponse
    {
        abort_unless($schema->profileReady(), 404);
        $store = $request->user()->currentStore();
        $contact = $store->contacts()->findOrFail($contact);

        if (!$contact->whatsapp_url) {
            return back()->with('error', "{$contact->name} no tiene celular registrado.");
        }

        $template = $templates->find($store, (string) $request->query('plantilla', 'follow_up'));
        abort_unless($template, 404);
        if ($template['promo'] && !$contact->accepts_marketing) {
            return back()->with('error', "«{$template['name']}» es una plantilla de ofertas y {$contact->name} no aceptó recibirlas (Ley 29733). Usa un mensaje de servicio.");
        }

        if (str_contains($template['body'], '{saldo}') && !$templates->hasBalance($contact)) {
            return back()->with('error', "{$contact->name} no tiene saldo pendiente: «{$template['name']}» saldría sin monto.");
        }

        $product = $request->filled('producto') ? $store->products()->withTrashed()->find($request->integer('producto')) : null;
        $text = $templates->render($template['body'], $contact, $product);

        $contacts->log($contact, 'whatsapp', "Mensaje «{$template['name']}»: " . Str::limit($text, 400), $request->user()->id);

        return redirect()->away($templates->whatsappUrl($contact, $text));
    }
}
