# Production database access

Production MySQL is published only on the VM loopback interface. Never add a
public firewall rule for port 3309 or change the Compose binding to `0.0.0.0`.

## Open the tunnel

Keep this command running locally while DBeaver is connected:

```bash
gcloud compute ssh instance-20260902-070042 \
  --zone=us-central1-a \
  --project=gridpbx-demo \
  --ssh-flag="-N" \
  --ssh-flag="-L 13306:127.0.0.1:3309"
```

## Configure DBeaver

- Driver: MySQL
- Host: `127.0.0.1`
- Port: `13306`
- Database: `gridpbx`
- Username: the dedicated database user
- Password: the dedicated database password
- DBeaver SSH tunnel: disabled, because `gcloud` owns the tunnel

The production bootstrap stores the initial operator connection values in
`/opt/gridpbx/dbeaver.credentials` with root-only permissions. Retrieve them in
your own terminal and never paste them into source control, tickets, or chat:

```bash
gcloud compute ssh instance-20260902-070042 \
  --zone=us-central1-a \
  --project=gridpbx-demo \
  --command='sudo cat /opt/gridpbx/dbeaver.credentials'
```

The operator account is limited to the `gridpbx` schema and must never receive
global MySQL administrative privileges. Create separate read-only accounts for
clients and reporting tools. Application data should normally be changed through
GridPBX workflows, while schema changes must continue to use migrations.
