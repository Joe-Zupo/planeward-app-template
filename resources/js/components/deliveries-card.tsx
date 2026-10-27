import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import type { WebhookDeliveryJson } from "@/lib/types";

export function DeliveriesCard({
  deliveries,
}: {
  deliveries: WebhookDeliveryJson[];
}) {
  return (
    <Card>
      <CardHeader>
        <CardTitle>Recent webhooks</CardTitle>
        <CardDescription>
          The last 20 deliveries for this installation.
        </CardDescription>
      </CardHeader>
      <CardContent>
        {deliveries.length === 0 ? (
          <p className="text-sm text-muted-foreground">No deliveries yet.</p>
        ) : (
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Event</TableHead>
                <TableHead>Received</TableHead>
                <TableHead>Delivery id</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {deliveries.map((delivery) => (
                <TableRow key={delivery.deliveryId}>
                  <TableCell className="font-mono">{delivery.event}</TableCell>
                  <TableCell>
                    {new Date(delivery.receivedAt).toLocaleString()}
                  </TableCell>
                  <TableCell className="max-w-48 truncate font-mono text-muted-foreground">
                    {delivery.deliveryId}
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        )}
      </CardContent>
    </Card>
  );
}
