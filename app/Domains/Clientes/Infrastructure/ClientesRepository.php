<?php

namespace App\Domains\Clientes\Infrastructure;

use App\Domains\Clientes\Domain\Dto\ClienteParaEscribirDto;
use App\Domains\Clientes\Domain\Interfaces\IClientesRepository;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * IClientesRepository against SQL Server. It writes only oauth_clients, which API-IAM owns; the secret's
 * hash is written but never read (confidencial is computed in SQL).
 */
final class ClientesRepository implements IClientesRepository
{
    public function buscar(string $id): ?ClienteParaEscribirDto
    {
        $fila = $this->consulta()->where('oauth_clients.id', $id)->first($this->columnas());

        return $fila === null ? null : self::dto($fila);
    }

    public function buscarPorNombre(string $nombre): array
    {
        return $this->consulta()
            ->where('oauth_clients.name', $nombre)
            ->orderBy('applications.name')
            ->orderBy('oauth_clients.id')
            ->get($this->columnas())
            ->map(fn (object $fila) => self::dto($fila))
            ->all();
    }

    public function cambiarSecreto(string $id, string $hash): void
    {
        DB::table('oauth_clients')->where('id', $id)->update(['secret' => $hash, 'updated_at' => now()]);
    }

    public function marcarRevocado(string $id, bool $revocado): bool
    {
        return DB::table('oauth_clients')
            ->where('id', $id)
            ->where('revoked', ! $revocado)
            ->update(['revoked' => $revocado, 'updated_at' => now()]) > 0;
    }

    private function consulta(): Builder
    {
        return DB::table('oauth_clients')->join('applications', 'applications.id', '=', 'oauth_clients.application_id');
    }

    /** @return list<mixed> */
    private function columnas(): array
    {
        return [
            'oauth_clients.id', 'oauth_clients.name', 'applications.name as aplicacion', 'oauth_clients.revoked',
            DB::raw('CASE WHEN oauth_clients.secret IS NULL THEN 0 ELSE 1 END AS confidencial'),
        ];
    }

    private static function dto(object $fila): ClienteParaEscribirDto
    {
        return new ClienteParaEscribirDto(Str::lower($fila->id), $fila->name, $fila->aplicacion, (bool) $fila->confidencial, (bool) $fila->revoked);
    }
}
