"""Test suite for Antigravity A2UI FastAPI Server, Whitelisted Catalogs, and CoA OpenAPI endpoints.
"""

import json
import pytest
from httpx import ASGITransport, AsyncClient

from ai_agent.antigravity_a2ui_server import app, create_noc_agent, render_noc_subscriber_surface
from ai_agent.noc_catalog import (
    NOC_CATALOG_ID,
    CoaDisconnectConfirmButton,
    OpticalSignalGauge,
    SubscriberSessionCard,
    create_noc_component_catalog,
)


@pytest.mark.asyncio
async def test_health_endpoint():
    async with AsyncClient(transport=ASGITransport(app=app), base_url="http://test") as ac:
        response = await ac.get("/health")
    assert response.status_code == 200
    data = response.json()
    assert data["status"] == "healthy"
    assert data["catalogId"] == NOC_CATALOG_ID


@pytest.mark.asyncio
async def test_capabilities_endpoint():
    async with AsyncClient(transport=ASGITransport(app=app), base_url="http://test") as ac:
        response = await ac.get("/capabilities")
    assert response.status_code == 200
    data = response.json()
    assert data["agent"] == "ISP NOC Copilot"
    assert "a2ui" in data
    assert "SubscriberSessionCard" in data["a2ui"]["components"]
    assert "OpticalSignalGauge" in data["a2ui"]["components"]
    assert "CoaDisconnectConfirmButton" in data["a2ui"]["components"]


@pytest.mark.asyncio
async def test_a2ui_catalog_schema_endpoint():
    async with AsyncClient(transport=ASGITransport(app=app), base_url="http://test") as ac:
        response = await ac.get("/api/v1/a2ui/catalog")
    assert response.status_code == 200
    data = response.json()
    assert data["catalogId"] == NOC_CATALOG_ID
    assert "components" in data
    assert "SubscriberSessionCard" in data["components"]
    assert "OpticalSignalGauge" in data["components"]
    assert "CoaDisconnectConfirmButton" in data["components"]


@pytest.mark.asyncio
async def test_coa_disconnect_openapi_endpoint():
    payload = {
        "username": "tapos_test",
        "nas_ip": "10.0.0.1",
        "acct_session_id": "sess-test-1234",
    }
    async with AsyncClient(transport=ASGITransport(app=app), base_url="http://test") as ac:
        response = await ac.post("/api/v1/coa/disconnect", json=payload)
    assert response.status_code == 200
    data = response.json()
    assert data["status"] == "success"
    assert data["username"] == "tapos_test"
    assert data["nas_ip"] == "10.0.0.1"
    assert "Disconnect-ACK received" in data["message"]


def test_render_noc_subscriber_surface():
    output = render_noc_subscriber_surface(
        username="cumilla_pop_01",
        ip_address="103.112.40.102",
        mac_address="00:1A:2B:3C:4D:5E",
        nas_ip="10.0.0.2",
        plan="50M/50M",
        rx_dbm=-20.8,
        status="active",
    )
    parsed = json.loads(output)
    assert "a2ui_operations" in parsed
    ops = parsed["a2ui_operations"]
    assert len(ops) == 2
    assert ops[0]["createSurface"]["surfaceId"] == "noc-subscriber-surface"
    assert ops[0]["createSurface"]["catalogId"] == NOC_CATALOG_ID

    components = ops[1]["updateComponents"]["components"]
    comp_types = [c["component"] for c in components]
    assert "SubscriberSessionCard" in comp_types
    assert "OpticalSignalGauge" in comp_types
    assert "CoaDisconnectConfirmButton" in comp_types


def test_catalog_components_validation():
    catalog = create_noc_component_catalog()
    assert catalog.catalog_id == NOC_CATALOG_ID
    schema = catalog.to_json()
    assert "components" in schema
    assert "SubscriberSessionCard" in schema["components"]
    assert "OpticalSignalGauge" in schema["components"]
    assert "CoaDisconnectConfirmButton" in schema["components"]
