<?php

namespace App\Http\Controllers;

use App\Models\BookletTemplate;
use App\Models\CardBatch;
use App\Models\Coupon;
use App\Models\MembershipCard;
use App\Services\ActivationCodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CardBatchController extends Controller
{
    public function index()
    {
        return Inertia::render('cards/Index', ['batches' => CardBatch::with('template:id,name')->withCount(['cards', 'cards as available_count' => fn ($q) => $q->where('status', 'available'), 'cards as activated_count' => fn ($q) => $q->where('status', 'activated')])->latest()->get(), 'cards' => MembershipCard::with(['member:id,name,member_code', 'batch:id,name'])->latest()->limit(100)->get(['id', 'activation_code_last4', 'card_batch_id', 'status', 'activated_by_user_id', 'activated_at', 'expires_at']), 'coupons' => Coupon::where('is_active', true)->orderBy('name')->get(['id', 'name'])]);
    }

    public function store(Request $request, ActivationCodeService $codes)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100', 'unique:card_batches,name'], 'quantity' => ['required', 'integer', 'min:1', 'max:5000'], 'expires_at' => ['nullable', 'date', 'after:today'], 'coupon_quantities' => ['required', 'array', 'min:1'], 'coupon_quantities.*' => ['integer', 'min:0', 'max:50']]);
        $selected = collect($data['coupon_quantities'])->filter(fn ($qty) => $qty > 0);
        if ($selected->isEmpty()) {
            return back()->withErrors(['coupon_quantities' => 'Selecciona al menos un cupón.']);
        }
        [$batch,$plainCodes] = DB::transaction(function () use ($data, $selected, $request, $codes) {
            $template = BookletTemplate::create(['name' => $data['name'], 'description' => 'Composición del lote '.$data['name'], 'version' => 1, 'is_active' => true]);
            $position = 0;
            foreach ($selected as $couponId => $qty) {
                $template->items()->create(['coupon_id' => $couponId, 'quantity' => $qty, 'position' => ++$position]);
            }
            $batch = CardBatch::create(['name' => $data['name'], 'booklet_template_id' => $template->id, 'quantity' => $data['quantity'], 'status' => 'active', 'created_by' => $request->user()->id]);
            $plain = [];
            for ($i = 0; $i < $data['quantity']; $i++) {
                do {
                    $code = $codes->generate();
                    $hash = $codes->hash($code);
                } while (MembershipCard::where('activation_code_hash', $hash)->exists());
                $plain[] = $code;
                MembershipCard::create(['activation_code_hash' => $hash, 'activation_code_encrypted' => Crypt::encryptString($code), 'activation_code_last4' => substr($codes->normalize($code), -4), 'booklet_template_id' => $template->id, 'card_batch_id' => $batch->id, 'status' => 'available', 'expires_at' => $data['expires_at'] ?? null, 'created_by' => $request->user()->id]);
            }

            return [$batch, $plain];
        });

        return response()->streamDownload(function () use ($plainCodes) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['codigo_activacion', 'url_qr']);
            foreach ($plainCodes as $code) {
                fputcsv($out, [$code, route('membership.register')]);
            }fclose($out);
        }, 'lote-'.$batch->id.'-codigos.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function toggle(MembershipCard $card)
    {
        if (in_array($card->status, ['activated', 'cancelled'])) {
            return back()->withErrors(['card' => 'Una tarjeta activada o cancelada no puede cambiarse.']);
        } $card->update(['status' => $card->status === 'blocked' ? 'available' : 'blocked']);

        return back()->with('success', 'Estado de tarjeta actualizado.');
    }

    public function export(CardBatch $batch, ActivationCodeService $codes)
    {
        $plainCodes = DB::transaction(function () use ($batch, $codes) {
            $cards = MembershipCard::where('card_batch_id', $batch->id)->lockForUpdate()->orderBy('id')->get();
            if ($cards->contains(fn ($card) => ! $card->activation_code_encrypted && $card->status === 'activated')) {
                throw ValidationException::withMessages(['batch' => 'Este lote contiene tarjetas activadas cuyos códigos originales no son recuperables.']);
            }

            return $cards->map(function ($card) use ($codes) {
                if ($card->activation_code_encrypted) {
                    return Crypt::decryptString($card->activation_code_encrypted);
                }

                do {
                    $code = $codes->generate();
                    $hash = $codes->hash($code);
                } while (MembershipCard::where('activation_code_hash', $hash)->whereKeyNot($card->id)->exists());

                $card->update([
                    'activation_code_hash' => $hash,
                    'activation_code_encrypted' => Crypt::encryptString($code),
                    'activation_code_last4' => substr($codes->normalize($code), -4),
                ]);

                return $code;
            });
        });

        return $this->downloadCodes($batch, $plainCodes);
    }

    private function downloadCodes(CardBatch $batch, iterable $plainCodes)
    {
        return response()->streamDownload(function () use ($plainCodes) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['codigo_activacion', 'url_qr']);
            foreach ($plainCodes as $code) {
                fputcsv($out, [$code, route('membership.register')]);
            }
            fclose($out);
        }, 'lote-'.$batch->id.'-codigos.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
