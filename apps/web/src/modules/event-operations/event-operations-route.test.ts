import fs from 'node:fs';
import path from 'node:path';

import { describe, expect, it } from 'vitest';

import { routeImports } from '@/app/routing/route-preload';

describe('event operations route architecture', () => {
  it('registers the control room route outside the AdminLayout but behind ProtectedRoute', () => {
    const appSource = fs.readFileSync(path.resolve(__dirname, '../../App.tsx'), 'utf8');

    expect(routeImports.eventOperationsRoom).toBeTypeOf('function');
    expect(appSource).toContain('const EventOperationsRoomPage = lazy(routeImports.eventOperationsRoom);');
    expect(appSource).toContain('path="events/:id/control-room"');
    expect(appSource).toContain('requiredPermissions={[\'operations.view\']}');

    const protectedRouteIndex = appSource.indexOf('<Route element={<ProtectedRoute />}>');
    const adminLayoutIndex = appSource.indexOf('<Route element={<AdminLayout />}>');
    const controlRoomIndex = appSource.indexOf('path="events/:id/control-room"');

    expect(protectedRouteIndex).toBeGreaterThan(-1);
    expect(controlRoomIndex).toBeGreaterThan(protectedRouteIndex);
    expect(controlRoomIndex).toBeLessThan(adminLayoutIndex);
  });
});
