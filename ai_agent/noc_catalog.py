"""ISP NOC Copilot Component Catalog for A2UI.

Whitelisted components:
1. SubscriberSessionCard: Displays subscriber session details (username, IP, MAC, NAS IP, tariff plan, status, uptime)
2. OpticalSignalGauge: Visual gauge for OLT/ONT optical Rx power in dBm with signal health classification
3. CoaDisconnectConfirmButton: Interactive button bound to POST /api/v1/coa/disconnect (RFC 5176 PoD Kick)
"""

from __future__ import annotations

import json
from typing import Any, Dict, List, Literal, Optional
from pydantic import ConfigDict, Field
from a2ui.core.catalog.catalog import Catalog
from a2ui.core.catalog.components import ModelComponentApi
from a2ui.core.schema.v0_9.common_types import ComponentCommon

NOC_CATALOG_ID = "https://a2ui.org/catalogs/isp-noc-catalog.json"
NOC_CATALOG_VERSION = "v0_9"


class SubscriberSessionCard(ComponentCommon):
    """Component for displaying an ISP subscriber active session in the NOC console."""

    component: Literal["SubscriberSessionCard"] = "SubscriberSessionCard"
    username: str = Field(..., description="Subscriber PPPoE/IPoE username")
    ip_address: str = Field(..., description="Framed IP address allocated to subscriber")
    mac_address: str = Field(..., description="Calling station MAC address")
    nas_ip: str = Field(..., description="BNG / NAS Router IP address")
    plan: str = Field(..., description="Bandwidth / Tariff plan (e.g., 20M/20M)")
    status: Literal["active", "expired", "suspended"] = Field(
        default="active", description="Session state"
    )
    uptime: Optional[str] = Field(
        default="3h 24m", description="PPPoE session connection uptime"
    )
    vlan_id: Optional[int] = Field(default=100, description="Subscriber access VLAN ID")


class OpticalSignalGauge(ComponentCommon):
    """Component for rendering an optical power (Rx dBm) gauge for GPON/EPON ONT/OLT."""

    component: Literal["OpticalSignalGauge"] = "OpticalSignalGauge"
    rx_dbm: float = Field(..., description="Measured optical Rx power in dBm")
    min_threshold_dbm: float = Field(
        default=-27.0, description="Minimum acceptable Rx sensitivity before loss of signal"
    )
    max_threshold_dbm: float = Field(
        default=-8.0, description="Maximum acceptable optical overload threshold"
    )
    status: Literal["optimal", "warning", "critical"] = Field(
        default="optimal", description="Signal health evaluation status"
    )
    olt_port: Optional[str] = Field(
        default="EPON0/1:14", description="OLT PON port and splitter identifier"
    )


class CoaDisconnectConfirmButton(ComponentCommon):
    """Component for executing RFC 5176 PoD Disconnect bound to POST /api/v1/coa/disconnect."""

    component: Literal["CoaDisconnectConfirmButton"] = "CoaDisconnectConfirmButton"
    username: str = Field(..., description="PPPoE username to disconnect")
    nas_ip: str = Field(..., description="BNG / NAS IP address")
    acct_session_id: Optional[str] = Field(
        default=None, description="Radius accounting session ID"
    )
    label: str = Field(
        default="PoD Kick (RFC 5176 UDP 3799)", description="Button label text"
    )
    api_endpoint: str = Field(
        default="/api/v1/coa/disconnect",
        description="Target REST API path defined in docs/openapi.yaml",
    )
    method: Literal["POST"] = Field(default="POST", description="HTTP method")
    payload: Dict[str, Any] = Field(
        default_factory=dict,
        description="Bound request body matching OpenAPI schema (username, nas_ip, acct_session_id)",
    )


def create_noc_component_catalog() -> Catalog:
    """Builds and returns the validated A2UI Catalog for the ISP NOC Copilot."""
    components = [
        ModelComponentApi(SubscriberSessionCard),
        ModelComponentApi(OpticalSignalGauge),
        ModelComponentApi(CoaDisconnectConfirmButton),
    ]
    return Catalog(
        catalog_id=NOC_CATALOG_ID,
        protocol_version=NOC_CATALOG_VERSION,
        components=components,
        title="ISP NOC Copilot Whitelisted Component Catalog",
        description="Strictly whitelisted A2UI components for ISP network operations, subscriber management, and RFC 5176 CoA actions.",
    )


def get_noc_catalog_schema() -> Dict[str, Any]:
    """Returns the serialized JSON Schema dictionary of the NOC component catalog."""
    catalog = create_noc_component_catalog()
    return catalog.to_json()


if __name__ == "__main__":
    catalog = create_noc_component_catalog()
    print(f"Catalog ID: {catalog.catalog_id}")
    print(json.dumps(catalog.to_json(), indent=2))
