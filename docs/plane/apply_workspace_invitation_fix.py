from pathlib import Path
import py_compile
import re
import sys


target = Path(sys.argv[1] if len(sys.argv) > 1 else "/code/plane/api/views/invite.py")
source = target.read_text(encoding="utf-8")

if 'response_data["email_dispatched"] = True' in source:
    py_compile.compile(str(target), doraise=True)
    print(f"La correccion ya estaba aplicada en {target}.")
    raise SystemExit(0)

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

required_symbols = [
    "WorkspaceInvitationsViewset",
    "WorkspaceMemberInvite",
    "Workspace",
    "WorkspaceInviteSerializer",
    "Response",
    "status",
]
missing_symbols = [symbol for symbol in required_symbols if symbol not in source]
if missing_symbols:
    raise SystemExit(
        "No se modifico Plane: faltan simbolos esperados en esta version: "
        + ", ".join(missing_symbols)
    )

class_match = re.search(r"(?m)^class WorkspaceInvitationsViewset\b", source)
if not class_match:
    raise SystemExit("No se modifico Plane: no se encontro la clase de invitaciones.")

class_source = source[class_match.start():]
create_match = re.search(
    r"(?ms)^    def create\(self,\s*request,\s*slug\):\n.*?(?=^    (?:@|def\s))",
    class_source,
)
if not create_match:
    raise SystemExit("No se modifico Plane: no se encontro el metodo create de invitaciones.")

start = class_match.start() + create_match.start()
end = class_match.start() + create_match.end()
patched = source[:start] + create_replacement + source[end:]

required_imports = [
    "from datetime import datetime",
    "import jwt",
    "from django.conf import settings",
    "from plane.bgtasks.workspace_invitation_task import workspace_invitation",
    "from plane.utils.host import base_host",
]
missing_imports = [line for line in required_imports if line not in patched]
if missing_imports:
    insertion = "\n".join(missing_imports) + "\n\n"
    future_imports = list(re.finditer(r"(?m)^from __future__ import .+$", patched))
    insert_at = future_imports[-1].end() + 1 if future_imports else 0
    patched = patched[:insert_at] + insertion + patched[insert_at:]

compile(patched, str(target), "exec")

backup = target.with_suffix(target.suffix + ".orbit-backup")
if not backup.exists():
    backup.write_text(source, encoding="utf-8")

target.write_text(patched, encoding="utf-8")
py_compile.compile(str(target), doraise=True)
print(f"Correccion aplicada y validada en {target}.")
