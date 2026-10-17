<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['user_id', 'action', 'auditable_type', 'auditable_id', 'metadata', 'ip_address', 'user_agent'])]
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Human-readable label in Spanish for the action.
     */
    public function actionLabel(): string
    {
        return match ($this->action) {
            'auth.login' => 'Inicio de sesión',
            'auth.logout' => 'Cierre de sesión',
            'auth.code_sent' => 'Código de acceso enviado',
            'auth.code_failed' => 'Código incorrecto',
            'auth.login_after_payment' => 'Ingreso tras compra',
            'reader.opened' => 'Lector abierto',
            'reader.progress' => 'Avance de lectura',
            'payment.started' => 'Pago iniciado',
            'payment.paid' => 'Pago confirmado',
            'payment.failed' => 'Pago rechazado/fallido',
            'book.purchased' => 'Libro comprado',
            'book.access_granted' => 'Acceso otorgado',
            'book.access_revoked' => 'Acceso revocado',
            'book.created' => 'Libro creado',
            'book.updated' => 'Libro modificado',
            'book.published' => 'Libro publicado',
            'book.deactivated' => 'Libro desactivado',
            'book.deleted' => 'Libro eliminado',
            'customer.activated' => 'Usuario activado',
            'customer.deactivated' => 'Usuario desactivado',
            'subscription.courtesy_extended' => 'Cortesía extendida',
            'subscription.cancelled' => 'Suscripción cancelada',
            'subscription.resumed' => 'Suscripción reactivada',
            'subscription.revoked' => 'Suscripción revocada',
            'author.created' => 'Autor creado',
            'author.updated' => 'Autor modificado',
            'author.deleted' => 'Autor eliminado',
            'category.created' => 'Categoría creada',
            'category.updated' => 'Categoría modificada',
            'category.deleted' => 'Categoría eliminada',
            'expense.created' => 'Gasto registrado',
            'expense.updated' => 'Gasto editado',
            'expense.deleted' => 'Gasto eliminado',
            'plan.created' => 'Plan creado',
            'plan.updated' => 'Plan editado',
            'settings.mail_tested' => 'Prueba de correo',
            'settings.bold_tested' => 'Prueba de pasarela Bold',
            'finance.exported' => 'Reporte financiero exportado',
            'backup.created' => 'Copia de seguridad creada',
            'interaction.admin' => 'Interacción administrativa',
            'interaction.user' => 'Navegación de usuario',
            default => str_replace(['.', '_'], [' ', ' '], $this->action),
        };
    }

    /**
     * Categorize action for filtering.
     */
    public function category(): string
    {
        return match (true) {
            str_starts_with($this->action, 'auth.') => 'auth',
            str_starts_with($this->action, 'reader.') => 'reading',
            str_starts_with($this->action, 'payment.') || in_array($this->action, ['book.purchased', 'subscription.cancelled', 'subscription.resumed', 'subscription.courtesy_extended']) => 'payment',
            str_starts_with($this->action, 'book.') || str_starts_with($this->action, 'author.') || str_starts_with($this->action, 'category.') || str_starts_with($this->action, 'plan.') => 'content',
            str_starts_with($this->action, 'interaction.admin') => 'admin',
            str_starts_with($this->action, 'interaction.user') => 'navigation',
            default => 'system',
        };
    }

    /**
     * Badge CSS classes for styling.
     */
    public function badgeClasses(): string
    {
        return match ($this->category()) {
            'auth' => 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20',
            'reading' => 'bg-sky-500/10 text-sky-600 dark:text-sky-400 border-sky-500/20',
            'payment' => 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20',
            'content' => 'bg-purple-500/10 text-purple-600 dark:text-purple-400 border-purple-500/20',
            'admin' => 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border-indigo-500/20',
            'navigation' => 'bg-zinc-500/10 text-zinc-600 dark:text-zinc-400 border-zinc-500/20',
            default => 'bg-zinc-500/10 text-zinc-600 dark:text-zinc-400 border-zinc-500/20',
        };
    }
}
