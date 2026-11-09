<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Spec operacion-del-servicio: identificador de traza en cada respuesta.
 */
class TracingTest extends TestCase
{
    protected const TRACE_ID = '4bf92f3577b34da6a3ce929d0e0e4736';

    public function test_responses_continue_the_callers_trace(): void
    {
        $this->withHeader('traceparent', '00-'.self::TRACE_ID.'-00f067aa0ba902b7-01')
            ->getJson('/api/iam/v1/salud')
            ->assertOk()
            ->assertHeader('X-Trace-Id', self::TRACE_ID);
    }

    public function test_a_request_without_traceparent_gets_a_new_trace(): void
    {
        $traceId = $this->getJson('/api/iam/v1/salud')->assertOk()->headers->get('X-Trace-Id');

        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', (string) $traceId);
        $this->assertNotSame(self::TRACE_ID, $traceId);
    }

    public function test_error_responses_carry_the_trace_id(): void
    {
        $this->withHeader('traceparent', '00-'.self::TRACE_ID.'-00f067aa0ba902b7-01')
            ->getJson('/api/iam/v1/no-existe')
            ->assertNotFound()
            ->assertHeader('X-Trace-Id', self::TRACE_ID);
    }
}
