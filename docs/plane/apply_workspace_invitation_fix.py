from pathlib import Path
import py_compile
import sys


target = Path(sys.argv[1] if len(sys.argv) > 1 else "/code/plane/api/views/invite.py")
source = target.read_text(encoding="utf-8")

if 'response_data["email_dispatched"] = True' in source:
    py_compile.compile(str(target), doraise=True)
    print(f"La correccion ya estaba aplicada en {target}.")
    raise SystemExit(0)

imports_marker = """# See the LICENSE file for details.

# Third party imports
"""
imports_replacement = """# See the LICENSE file for details.

# Python imports
from datetime import datetime

import jwt

# Django imports
from django.conf import settings

# Third party imports
"""

module_marker = """from plane.api.views.base import BaseViewSet
from plane.db.models import WorkspaceMemberInvite, Workspace
from plane.api.serializers import WorkspaceInviteSerializer
from plane.utils.permissions import WorkspaceOwnerPermission
"""
module_replacement = """from plane.api.views.base import BaseViewSet
from plane.bgtasks.workspace_invitation_task import workspace_invitation
from plane.db.models import WorkspaceMemberInvite, Workspace
from plane.api.serializers import WorkspaceInviteSerializer
from plane.utils.host import base_host
from plane.utils.permissions import WorkspaceOwnerPermission
"""

create_marker = """    def create(self, request, slug):
        workspace = Workspace.objects.get(slug=slug)
        serializer = WorkspaceInviteSerializer(data=request.data, context={"slug": slug})
        serializer.is_valid(raise_exception=True)
        serializer.save(workspace=workspace, created_by=request.user)
        return Response(serializer.data, status=status.HTTP_201_CREATED)
"""
create_replacement = """    def create(self, request, slug):
        workspace = Workspace.objects.get(slug=slug)
        email = str(request.data.get("email", "")).strip().lower()
        invitation = WorkspaceMemberInvite.objects.filter(
            workspace=workspace,
            email=email,
            accepted=False,
            responded_at__isnull=True,
        ).first()

        token = jwt.encode(
            {
                "email": email,
                "timestamp": datetime.now().timestamp(),
            },
            settings.SECRET_KEY,
            algorithm="HS256",
        )

        if invitation:
            invitation.token = token
            invitation.role = request.data.get("role", invitation.role)
            invitation.created_by = request.user
            invitation.save(update_fields=["token", "role", "created_by", "updated_at"])
            serializer = WorkspaceInviteSerializer(invitation)
        else:
            serializer = WorkspaceInviteSerializer(data=request.data, context={"slug": slug})
            serializer.is_valid(raise_exception=True)
            invitation = serializer.save(
                workspace=workspace,
                created_by=request.user,
                token=token,
            )

        workspace_invitation.delay(
            invitation.email,
            workspace.id,
            invitation.token,
            base_host(request=request, is_app=True),
            request.user.email,
        )

        response_data = dict(serializer.data)
        response_data["token_generated"] = True
        response_data["email_dispatched"] = True

        return Response(response_data, status=status.HTTP_201_CREATED)
"""

markers = [imports_marker, module_marker, create_marker]
missing = [str(index + 1) for index, marker in enumerate(markers) if marker not in source]
if missing:
    raise SystemExit(
        "No se modifico Plane: la version instalada no coincide con los marcadores "
        + ", ".join(missing)
        + "."
    )

patched = source.replace(imports_marker, imports_replacement, 1)
patched = patched.replace(module_marker, module_replacement, 1)
patched = patched.replace(create_marker, create_replacement, 1)

backup = target.with_suffix(target.suffix + ".orbit-backup")
if not backup.exists():
    backup.write_text(source, encoding="utf-8")

target.write_text(patched, encoding="utf-8")
py_compile.compile(str(target), doraise=True)
print(f"Correccion aplicada y validada en {target}.")
