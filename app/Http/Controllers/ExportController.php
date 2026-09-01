<?php

namespace App\Http\Controllers;

use App\Models\CouponAssignment;
use App\Models\MembershipCard;
use App\Models\User;

class ExportController extends Controller
{
    public function members()
    {
        $rows = User::where('role', 'member')->with(['membershipCard.batch', 'booklet'])->withCount(['couponAssignments', 'couponAssignments as available_count' => fn ($q) => $q->where('status', 'available'), 'couponAssignments as redeemed_count' => fn ($q) => $q->where('status', 'redeemed')])->orderBy('name')->get();

        return response()->streamDownload(function () use ($rows) {
            $o = fopen('php://output', 'w');
            fwrite($o, "\xEF\xBB\xBF");
            fputcsv($o, ['Código', 'Nombre', 'Correo', 'Celular', 'Estado', 'Registro', 'Tarjeta', 'Lote', 'Total', 'Disponibles', 'Consumidos']);
            foreach ($rows as $u) {
                fputcsv($o, [$u->member_code, $u->name, $u->email, $u->phone, $u->status, $u->created_at?->format('Y-m-d H:i'), '****'.($u->membershipCard?->activation_code_last4 ?? ''), $u->membershipCard?->batch?->name, $u->coupon_assignments_count, $u->available_count, $u->redeemed_count]);
            }fclose($o);
        }, 'socios-'.today()->format('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function summary()
    {
        $data = [['Métrica', 'Cantidad'], ['Socios activos', User::where('role', 'member')->where('status', 'active')->count()], ['Tarjetas disponibles', MembershipCard::where('status', 'available')->count()], ['Tarjetas activadas', MembershipCard::where('status', 'activated')->count()], ['Cupones disponibles', CouponAssignment::where('status', 'available')->count()], ['Cupones consumidos', CouponAssignment::where('status', 'redeemed')->count()]];

        return response()->streamDownload(function () use ($data) {
            $o = fopen('php://output', 'w');
            fwrite($o, "\xEF\xBB\xBF");
            foreach ($data as $r) {
                fputcsv($o, $r);
            }fclose($o);
        }, 'resumen-'.today()->format('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
