#!/bin/bash

# ========================================================================
# SCRIPT DE VALIDACIÓN - DOTSBOX
# ========================================================================
# Este script verifica que la solución esté implementada correctamente
# y que el sistema funcione según lo esperado.
# ========================================================================

echo "╔══════════════════════════════════════════════════════════════╗"
echo "║         SCRIPT DE VALIDACIÓN - SISTEMA DOTSBOX              ║"
echo "╚══════════════════════════════════════════════════════════════╝"
echo ""

# Variables (AJUSTAR SEGÚN TU CONFIGURACIÓN)
ESP32_IP="192.168.0.24"
SERVIDOR_PHP="192.168.0.23"
PROYECTO_PATH="/dots_proy"

# Colores
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Contadores
TESTS_PASSED=0
TESTS_FAILED=0

# Función para mostrar resultados
print_test() {
    local test_name="$1"
    local result="$2"
    
    if [ "$result" = "PASS" ]; then
        echo -e "${GREEN}✓${NC} $test_name"
        ((TESTS_PASSED++))
    elif [ "$result" = "FAIL" ]; then
        echo -e "${RED}✗${NC} $test_name"
        ((TESTS_FAILED++))
    else
        echo -e "${YELLOW}⚠${NC} $test_name"
    fi
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "📡 TEST 1: Conectividad ESP32"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Test 1.1: Ping al ESP32
if ping -c 1 -W 2 "$ESP32_IP" > /dev/null 2>&1; then
    print_test "Ping al ESP32 ($ESP32_IP)" "PASS"
else
    print_test "Ping al ESP32 ($ESP32_IP)" "FAIL"
    echo "  ⚠️  Verifica que el ESP32 esté encendido y conectado a la red"
fi

# Test 1.2: Endpoint raíz del ESP32
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "http://$ESP32_IP/" --max-time 5)
if [ "$HTTP_CODE" = "200" ]; then
    print_test "Endpoint raíz (/)" "PASS"
else
    print_test "Endpoint raíz (/) - HTTP $HTTP_CODE" "FAIL"
fi

# Test 1.3: Endpoint /status
STATUS_RESPONSE=$(curl -s "http://$ESP32_IP/status" --max-time 5)
if echo "$STATUS_RESPONSE" | grep -q "wifi"; then
    print_test "Endpoint /status" "PASS"
    
    # Verificar estado WiFi
    if echo "$STATUS_RESPONSE" | grep -q '"wifi":true'; then
        print_test "  └─ WiFi conectado" "PASS"
    else
        print_test "  └─ WiFi conectado" "FAIL"
    fi
else
    print_test "Endpoint /status" "FAIL"
fi

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "🔧 TEST 2: Endpoints del ESP32"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Test 2.1: /leer_huella
echo "⏳ Probando /leer_huella (timeout 12s)..."
HUELLA_RESPONSE=$(curl -s "http://$ESP32_IP/leer_huella" --max-time 12)
if echo "$HUELLA_RESPONSE" | grep -q "huella_id"; then
    print_test "Endpoint /leer_huella" "PASS"
else
    print_test "Endpoint /leer_huella" "FAIL"
    echo "  ⚠️  Este test puede fallar si no hay huella en el sensor"
fi

# Test 2.2: /leer_peso
echo "⏳ Probando /leer_peso..."
PESO_RESPONSE=$(curl -s "http://$ESP32_IP/leer_peso" --max-time 15)
if echo "$PESO_RESPONSE" | grep -q "peso"; then
    print_test "Endpoint /leer_peso" "PASS"
else
    print_test "Endpoint /leer_peso" "FAIL"
fi

# Test 2.3: /leer_saturacion
SATURACION_RESPONSE=$(curl -s "http://$ESP32_IP/leer_saturacion" --max-time 5)
if echo "$SATURACION_RESPONSE" | grep -q "saturacion"; then
    print_test "Endpoint /leer_saturacion" "PASS"
else
    print_test "Endpoint /leer_saturacion" "FAIL"
fi

# Test 2.4: /actualizar_relay (NUEVO)
RELAY_RESPONSE=$(curl -s "http://$ESP32_IP/check_relay?gabinete_id=1" --max-time 5)
if echo "$RELAY_RESPONSE" | grep -q "success"; then
    print_test "Endpoint /check_relay (NUEVO)" "PASS"
else
    print_test "Endpoint /check_Relay (NUEVO)" "FAIL"
    echo "  ⚠️  Este endpoint debe estar implementado en el código corregido"
fi

# Test 2.5: /dispensar
DISPENSAR_RESPONSE=$(curl -s "http://$ESP32_IP/dispensar" --max-time 5)
if echo "$DISPENSAR_RESPONSE" | grep -q "success"; then
    print_test "Endpoint /dispensar" "PASS"
else
    print_test "Endpoint /dispensar" "FAIL"
fi

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "🌐 TEST 3: Conectividad Servidor PHP"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Test 3.1: Ping al servidor
if ping -c 1 -W 2 "$SERVIDOR_PHP" > /dev/null 2>&1; then
    print_test "Ping al servidor PHP ($SERVIDOR_PHP)" "PASS"
else
    print_test "Ping al servidor PHP ($SERVIDOR_PHP)" "FAIL"
fi

# Test 3.2: api_get_relay.php
API_RELAY_RESPONSE=$(curl -s "http://$SERVIDOR_PHP$PROYECTO_PATH/api_get_relay.php?gabinete_id=1" --max-time 5)
if echo "$API_RELAY_RESPONSE" | grep -q "relay"; then
    print_test "API api_get_relay.php" "PASS"
else
    print_test "API api_get_relay.php" "FAIL"
fi

# Test 3.3: esp32_enviar_datos.php
ESP32_DATA_RESPONSE=$(curl -s -X POST "http://$SERVIDOR_PHP$PROYECTO_PATH/esp32_enviar_datos.php" \
    -H "Content-Type: application/json" \
    -d '{"gabinete_id":1,"temperatura":25.0,"humedad":60.0}' \
    --max-time 5)
if echo "$ESP32_DATA_RESPONSE" | grep -q "success"; then
    print_test "API esp32_enviar_datos.php" "PASS"
else
    print_test "API esp32_enviar_datos.php" "FAIL"
fi

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "🔄 TEST 4: Flujo de Control de Relay"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

echo "⏳ Simulando cambio de relay UV..."

# Test 4.1: Obtener estado actual
ESTADO_INICIAL=$(curl -s "http://$SERVIDOR_PHP$PROYECTO_PATH/api_get_relay.php?gabinete_id=1" --max-time 5)
if echo "$ESTADO_INICIAL" | grep -q '"relay"'; then
    RELAY_STATE=$(echo "$ESTADO_INICIAL" | grep -o '"relay":[0-1]' | cut -d':' -f2)
    print_test "Obtener estado inicial del relay" "PASS"
    echo "  └─ Estado actual: $RELAY_STATE"
else
    print_test "Obtener estado inicial del relay" "FAIL"
    RELAY_STATE="0"
fi

# Test 4.2: Notificar ESP32 para actualizar relay
echo "  └─ Notificando al ESP32..."
NOTIFY_RESPONSE=$(curl -s "http://$ESP32_IP/actualizar_relay?gabinete_id=1" --max-time 5)
if echo "$NOTIFY_RESPONSE" | grep -q "success"; then
    print_test "Notificación al ESP32" "PASS"
else
    print_test "Notificación al ESP32" "FAIL"
fi

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "✅ TEST 5: Verificación de Implementación"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Test 5.1: Verificar que actualizar_relay existe
if echo "$RELAY_RESPONSE" | grep -q "actualizar_relay"; then
    print_test "Endpoint /actualizar_relay implementado" "PASS"
else
    print_test "Endpoint /actualizar_relay implementado" "FAIL"
    echo "  ⚠️  Verifica que subiste el código corregido al ESP32"
fi

# Test 5.2: Verificar respuesta JSON correcta
if echo "$RELAY_RESPONSE" | grep -q '"success":true'; then
    print_test "Respuesta JSON correcta" "PASS"
else
    print_test "Respuesta JSON correcta" "FAIL"
fi

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "📊 RESUMEN DE PRUEBAS"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

TOTAL_TESTS=$((TESTS_PASSED + TESTS_FAILED))
PASS_RATE=$((TESTS_PASSED * 100 / TOTAL_TESTS))

echo ""
echo "Total de pruebas: $TOTAL_TESTS"
echo -e "${GREEN}Pruebas exitosas: $TESTS_PASSED${NC}"
echo -e "${RED}Pruebas fallidas: $TESTS_FAILED${NC}"
echo "Tasa de éxito: $PASS_RATE%"
echo ""

# Variable para guardar el código de salida final
EXIT_CODE=0

if [ $TESTS_FAILED -eq 0 ]; then
    echo -e "${GREEN}╔══════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${GREEN}║  🎉 ¡TODAS LAS PRUEBAS PASARON!                             ║${NC}"
    echo -e "${GREEN}║  Tu sistema DotsBox está correctamente configurado.         ║${NC}"
    echo -e "${GREEN}╚══════════════════════════════════════════════════════════════╝${NC}"
    EXIT_CODE=0
elif [ $PASS_RATE -ge 80 ]; then
    echo -e "${YELLOW}╔══════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${YELLOW}║  ⚠️  ALGUNAS PRUEBAS FALLARON                               ║${NC}"
    echo -e "${YELLOW}║  El sistema funciona pero necesita ajustes.                 ║${NC}"
    echo -e "${YELLOW}║  Revisa los tests fallidos arriba.                          ║${NC}"
    echo -e "${YELLOW}╚══════════════════════════════════════════════════════════════╝${NC}"
    EXIT_CODE=1
else
    echo -e "${RED}╔══════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${RED}║  ❌ MÚLTIPLES PRUEBAS FALLARON                               ║${NC}"
    echo -e "${RED}║  Verifica la implementación de la solución.                  ║${NC}"
    echo -e "${RED}║  Consulta SOLUCION_COMPLETA.md para troubleshooting.        ║${NC}"
    echo -e "${RED}╚══════════════════════════════════════════════════════════════╝${NC}"
    EXIT_CODE=2
fi

# 🔹 Pausa final para que no se cierre la ventana
echo ""
read -p "Presiona ENTER para cerrar este script..." _

exit $EXIT_CODE

