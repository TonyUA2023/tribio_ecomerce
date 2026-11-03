<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\SaveContactTaskRequest;
use App\Models\Contact;
use App\Models\ContactTask;
use App\Services\Crm\ContactService;
use App\Services\Crm\CrmSchema;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Dashboard → Clientes → Seguimientos: follow-ups with a date, overdue first. */
class ContactTaskController extends Controller
{
    public function __construct(private CrmSchema $schema)
    {
    }

    public function index(Request $request)
    {
        abort_unless($this->schema->profileReady(), 404);
        $store = Auth::user()->currentStore();
        $pending = ContactTask::where('store_id', $store->id)->pending()->with(['contact:id,name,phone', 'product:id,name'])->orderBy('due_at')->get();

        return view('dashboard.contacts.tasks', [
            'overdue'  => $pending->filter(fn ($t) => $t->due_at->lt(now()->startOfDay()))->values(),
            'today'    => $pending->filter(fn ($t) => $t->due_at->isToday())->values(),
            'upcoming' => $pending->filter(fn ($t) => $t->due_at->gt(now()->endOfDay()))->values(),
            'done'     => ContactTask::where('store_id', $store->id)->whereNotNull('done_at')->with('contact:id,name')
                ->latest('done_at')->limit(10)->get(),
            'contacts' => $store->contacts()->orderBy('name')->limit(500)->get(['id', 'name']),
        ]);
    }

    public function store(SaveContactTaskRequest $request, ContactService $service): RedirectResponse
    {
        abort_unless($this->schema->profileReady(), 404);
        $store = $request->user()->currentStore();
        $contact = $request->filled('contact_id') ? $store->contacts()->findOrFail($request->integer('contact_id')) : null;
        $service->addTask($store, $contact, $request->validated(), $request->user()->id);

        return back()->with('success', 'Agendamos el seguimiento.');
    }

    public function complete(Request $request, ContactService $service, int $task): RedirectResponse
    {
        $request->validate(['outcome' => ['nullable', 'string', 'max:500']]);
        $task = $this->owned($task);
        if ($task->done_at) {
            $service->reopenTask($task);

            return back()->with('success', 'Volvimos a abrir el seguimiento.');
        }
        $service->completeTask($task, $request->input('outcome'), $request->user()->id);

        return back()->with('success', 'Seguimiento hecho.');
    }

    public function destroy(int $task): RedirectResponse
    {
        $this->owned($task)->delete();

        return back()->with('success', 'Eliminamos el seguimiento.');
    }

    private function owned(int $id): ContactTask
    {
        abort_unless($this->schema->profileReady(), 404);

        return ContactTask::where('store_id', Auth::user()->currentStore()->id)->findOrFail($id);
    }
}
