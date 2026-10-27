/**
 * JSON shapes returned by this app's API (`app/Http/Resources`). Dates are
 * ISO 8601 strings.
 */

export type PostJson = {
  id: string;
  installationId: string;
  channelId: string;
  authorId: string;
  authorName: string;
  body: string;
  createdAt: string;
};

export type InstallationJson = {
  id: string;
  workspaceId: string;
  workspaceName: string;
  grantedPermissions: string[];
  installedAt: string;
  uninstalledAt: string | null;
};

export type WebhookDeliveryJson = {
  deliveryId: string;
  event: string;
  installationId: string;
  receivedAt: string;
};
