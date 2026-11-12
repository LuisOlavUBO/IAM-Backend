<?php

namespace App\Domains\Clientes\Infrastructure;

use App\Domains\Clientes\Domain\Dto\ClienteParaEscribirDto;
use App\Domains\Clientes\Domain\Dto\NuevoClienteDto;
use App\Domains\Clientes\Domain\Dto\RolDeAplicacionDto;
use App\Domains\Clientes\Domain\Interfaces\IClientesRepository;
use App\Domains\Clientes\Domain\NuevoCliente;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * IClientesRepository against SQL Server. It writes only oauth_clients and oauth_client_role, which API-IAM
 * owns; the secret's hash is written but never read (confidencial is computed in SQL).
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

    public function nombreEnUso(string $nombre): bool
    {
        // The CI collation of the IAM database ignores case (design D5 of clientes-crear).
        return DB::table('oauth_clients')->where('name', $nombre)->exists();
    }

    public function crear(NuevoClienteDto $cliente, int $aplicacionId): void
    {
        DB::table('oauth_clients')->insert([
            'id' => $cliente->id,
            'application_id' => $aplicacionId,
            'name' => $cliente->nombre,
            'secret' => $cliente->hashSecreto,
            'provider' => $cliente->provider,
            'redirect_uris' => json_encode($cliente->redirecciones),
            'grant_types' => json_encode($cliente->concesiones),
            'revoked' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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

    public function rol(string $aplicacion, string $nombre): ?RolDeAplicacionDto
    {
        $fila = DB::table('roles')
            ->join('applications', 'applications.id', '=', 'roles.application_id')
            ->where('applications.name', $aplicacion)
            ->where('roles.name', $nombre)
            ->first(['roles.id', 'applications.name as aplicacion', 'roles.name']);

        return $fila === null ? null : new RolDeAplicacionDto((int) $fila->id, $fila->aplicacion, $fila->name);
    }

    public function tieneRol(string $id, int $rolId): bool
    {
        return DB::table('oauth_client_role')->where('client_id', $id)->where('role_id', $rolId)->exists();
    }

    public function asignarRol(string $id, int $rolId): void
    {
        DB::table('oauth_client_role')->insert(['client_id' => $id, 'role_id' => $rolId]);
    }

    public function quitarRol(string $id, int $rolId): void
    {
        DB::table('oauth_client_role')->where('client_id', $id)->where('role_id', $rolId)->delete();
    }

    public function cambiarRedirecciones(string $id, array $redirecciones): void
    {
        DB::table('oauth_clients')->where('id', $id)->update([
            'redirect_uris' => json_encode(array_values($redirecciones)),
            'updated_at' => now(),
        ]);
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
            'oauth_clients.grant_types', 'oauth_clients.redirect_uris',
            DB::raw('CASE WHEN oauth_clients.secret IS NULL THEN 0 ELSE 1 END AS confidencial'),
        ];
    }

    private static function dto(object $fila): ClienteParaEscribirDto
    {
        $concesiones = self::lista($fila->grant_types);

        return new ClienteParaEscribirDto(
            Str::lower($fila->id),
            $fila->name,
            $fila->aplicacion,
            (bool) $fila->confidencial,
            (bool) $fila->revoked,
            match (true) {
                in_array('authorization_code', $concesiones, true) => NuevoCliente::AUTHORIZATION_CODE,
                in_array('client_credentials', $concesiones, true) => NuevoCliente::CLIENT_CREDENTIALS,
                default => null,
            },
            self::lista($fila->redirect_uris),
        );
    }

    /**
     * A JSON list of strings (grant_types, redirect_uris). Anything else counts as an empty list.
     *
     * @return list<string>
     */
    private static function lista(?string $json): array
    {
        $valor = json_decode((string) $json, true);

        return is_array($valor) && array_is_list($valor)
            ? array_values(array_filter($valor, 'is_string'))
            : [];
    }
}
