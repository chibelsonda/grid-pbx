#!/usr/bin/env bash
set -euo pipefail

project_id="${GCP_PROJECT_ID:-gridpbx-demo}"
repository="${GITHUB_REPOSITORY:-chibelsonda/grid-pbx}"
instance="${GCP_INSTANCE:-instance-20260902-070042}"
zone="${GCP_ZONE:-us-central1-a}"
pool_id="${GCP_WIF_POOL_ID:-github-actions}"
provider_id="${GCP_WIF_PROVIDER_ID:-gridpbx-main}"
service_account_id="${GCP_DEPLOY_SERVICE_ACCOUNT_ID:-github-gridpbx-deployer}"
service_account="${service_account_id}@${project_id}.iam.gserviceaccount.com"

project_number="$(gcloud projects describe "${project_id}" --format='value(projectNumber)')"
principal_set="principalSet://iam.googleapis.com/projects/${project_number}/locations/global/workloadIdentityPools/${pool_id}/attribute.repository/${repository}"
vm_service_account="$(gcloud compute instances describe "${instance}" --zone="${zone}" --project="${project_id}" --format='value(serviceAccounts[0].email)')"
operator="$(gcloud auth list --filter=status:ACTIVE --format='value(account)' | head -n 1)"

if [[ -z "${operator}" ]]; then
    echo "No active gcloud identity was found." >&2
    exit 1
fi

if [[ "${operator}" == *".gserviceaccount.com" ]]; then
    operator_member="serviceAccount:${operator}"
else
    operator_member="user:${operator}"
fi

gcloud services enable \
    compute.googleapis.com \
    iam.googleapis.com \
    iamcredentials.googleapis.com \
    sts.googleapis.com \
    --project="${project_id}"

if ! gcloud iam service-accounts describe "${service_account}" --project="${project_id}" >/dev/null 2>&1; then
    gcloud iam service-accounts create "${service_account_id}" \
        --display-name="GridPBX GitHub deployer" \
        --description="Keyless GitHub Actions identity for the GridPBX production VM" \
        --project="${project_id}"
fi

if ! gcloud iam workload-identity-pools describe "${pool_id}" \
    --location=global \
    --project="${project_id}" >/dev/null 2>&1; then
    gcloud iam workload-identity-pools create "${pool_id}" \
        --location=global \
        --display-name="GitHub Actions" \
        --description="Keyless deployment identities for GitHub Actions" \
        --project="${project_id}"
fi

if ! gcloud iam workload-identity-pools providers describe "${provider_id}" \
    --workload-identity-pool="${pool_id}" \
    --location=global \
    --project="${project_id}" >/dev/null 2>&1; then
    gcloud iam workload-identity-pools providers create-oidc "${provider_id}" \
        --workload-identity-pool="${pool_id}" \
        --location=global \
        --issuer-uri="https://token.actions.githubusercontent.com" \
        --attribute-mapping="google.subject=assertion.sub,attribute.repository=assertion.repository,attribute.ref=assertion.ref" \
        --attribute-condition="assertion.repository == '${repository}' && assertion.ref == 'refs/heads/main'" \
        --display-name="GridPBX main branch" \
        --project="${project_id}"
fi

gcloud iam service-accounts add-iam-policy-binding "${service_account}" \
    --role="roles/iam.workloadIdentityUser" \
    --member="${principal_set}" \
    --project="${project_id}" >/dev/null

# OS Login limits the privileged deployment identity to this VM and evaluates
# access on every connection instead of persisting project metadata SSH keys.
gcloud compute instances add-iam-policy-binding "${instance}" \
    --zone="${zone}" \
    --project="${project_id}" \
    --role="roles/compute.osAdminLogin" \
    --member="${operator_member}" >/dev/null

gcloud compute instances add-iam-policy-binding "${instance}" \
    --zone="${zone}" \
    --project="${project_id}" \
    --role="roles/compute.osAdminLogin" \
    --member="serviceAccount:${service_account}" >/dev/null

# gcloud needs read-only project discovery in addition to instance-scoped login.
gcloud projects add-iam-policy-binding "${project_id}" \
    --role="roles/compute.viewer" \
    --member="serviceAccount:${service_account}" \
    --condition=None >/dev/null

if [[ -n "${vm_service_account}" ]]; then
    gcloud iam service-accounts add-iam-policy-binding "${vm_service_account}" \
        --role="roles/iam.serviceAccountUser" \
        --member="serviceAccount:${service_account}" \
        --project="${project_id}" >/dev/null
fi

gcloud compute instances add-metadata "${instance}" \
    --zone="${zone}" \
    --project="${project_id}" \
    --metadata=enable-oslogin=TRUE >/dev/null

printf 'Workload Identity provider: projects/%s/locations/global/workloadIdentityPools/%s/providers/%s\n' \
    "${project_number}" "${pool_id}" "${provider_id}"
printf 'Deployment service account: %s\n' "${service_account}"
