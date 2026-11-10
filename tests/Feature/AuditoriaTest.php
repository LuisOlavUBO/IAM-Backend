<?php

namespace Tests\Feature;

use App\Shared\Auditoria\IAuditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use RuntimeException;
use Tests\TestCase;
use UBO\Common\Login\Testing\UboLogin;

/**
 * Spec auditoria.
 */
class AuditoriaTest extends TestCase
{
    use RefreshDatabase;

    private const ANA = '0190f2a4-3c1e-7d2b-9a6f-1b2c3d4e5f60';

    public function test_the_table_and_its_indexes_exist(): void
    {
        $this->assertTrue(Schema::hasTable('iam_auditoria'));

        $indexes = collect(DB::select("SELECT i.name FROM sys.indexes i WHERE i.object_id = OBJECT_ID('iam_auditoria') AND i.name IS NOT NULL"))
            ->pluck('name');

        $this->assertContains('iam_auditoria_entidad_tipo_entidad_id_ocurrido_en_index', $indexes);
        $this->assertContains('iam_auditoria_actor_uuid_ocurrido_en_index', $indexes);
        $this->assertContains('iam_auditoria_ocurrido_en_index', $indexes);
    }

    public function test_an_action_of_a_command_is_recorded(): void
    {
        DB::transaction(fn () => app(IAuditor::class)->registrar(
            'usuario.rol.asignacion.emergencia', 'usuario', self::ANA, null, ['rol' => 'iam-admin/administrador'],
        ));

        $entry = DB::table('iam_auditoria')->sole();

        $this->assertSame('comando', $entry->actor_tipo);
        $this->assertNull($entry->actor_uuid);
        $this->assertSame('usuario.rol.asignacion.emergencia', $entry->accion);
        $this->assertSame('usuario', $entry->entidad_tipo);
        $this->assertSame(self::ANA, $entry->entidad_id);
        $this->assertSame('ok', $entry->resultado);
        $this->assertSame(['rol' => 'iam-admin/administrador'], json_decode($entry->despues, true));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $entry->trace_id);
    }

    public function test_an_action_of_a_person_records_their_uuid(): void
    {
        UboLogin::actingAs(id: self::ANA, roles: ['administrador']);

        DB::transaction(fn () => app(IAuditor::class)->registrar('rol.creacion', 'rol', '7', null, ['nombre' => 'lector']));

        $entry = DB::table('iam_auditoria')->sole();
        $this->assertSame('persona', $entry->actor_tipo);
        $this->assertSame(self::ANA, strtolower($entry->actor_uuid));
    }

    public function test_an_entry_outside_a_transaction_is_refused(): void
    {
        $this->expectException(LogicException::class);

        // RefreshDatabase wraps the test in a transaction: leave it to check the guard.
        DB::rollBack();
        app(IAuditor::class)->registrar('rol.creacion', 'rol', '7');
    }

    public function test_the_entry_is_rolled_back_with_its_change(): void
    {
        try {
            DB::transaction(function () {
                app(IAuditor::class)->registrar('rol.creacion', 'rol', '7', null, ['nombre' => 'lector']);

                throw new RuntimeException('falla al guardar el cambio');
            });
        } catch (RuntimeException) {
        }

        $this->assertSame(0, DB::table('iam_auditoria')->count());
    }

    public function test_a_refused_attempt_is_recorded(): void
    {
        app(IAuditor::class)->rechazar('usuario.rol.asignacion', 'usuario', self::ANA, 'ESCALAMIENTO_DE_PRIVILEGIOS');

        $entry = DB::table('iam_auditoria')->sole();
        $this->assertSame('rechazado', $entry->resultado);
        $this->assertSame('ESCALAMIENTO_DE_PRIVILEGIOS', $entry->motivo);
        $this->assertNull($entry->despues);
    }

    public function test_secrets_are_never_written(): void
    {
        foreach ([['secret' => 'x'], ['datos' => ['password' => 'x']], ['Secreto' => 'x']] as $values) {
            try {
                DB::transaction(fn () => app(IAuditor::class)->registrar('cliente.creacion', 'cliente', 'id', null, $values));
                $this->fail('Debió rechazar el secreto.');
            } catch (LogicException) {
            }
        }

        $this->assertSame(0, DB::table('iam_auditoria')->count());
    }

    public function test_an_entry_does_not_depend_on_its_entity(): void
    {
        $applicationId = DB::table('applications')->insertGetId(['name' => 'portal']);
        $roleId = DB::table('roles')->insertGetId(['application_id' => $applicationId, 'name' => 'lector']);
        DB::transaction(fn () => app(IAuditor::class)->registrar('rol.creacion', 'rol', (string) $roleId, null, ['nombre' => 'lector']));

        DB::table('roles')->where('id', $roleId)->delete();

        $this->assertSame(1, DB::table('iam_auditoria')->where('entidad_id', (string) $roleId)->count());
    }
}
