import fs from 'node:fs';
import path from 'node:path';

import { describe, expect, it } from 'vitest';

describe('event media flow builder architecture characterization', () => {
  it('now installs @xyflow/react and exposes the dedicated event journey route with a canvas wrapper', () => {
    const packageJsonSource = fs.readFileSync(
      path.resolve(__dirname, '../../../package.json'),
      'utf8',
    );
    const appSource = fs.readFileSync(
      path.resolve(__dirname, '../../App.tsx'),
      'utf8',
    );
    const journeyPageSource = fs.readFileSync(
      path.resolve(__dirname, 'pages/EventJourneyBuilderPage.tsx'),
      'utf8',
    );

    expect(packageJsonSource).toContain('"@xyflow/react"');
    expect(appSource).toContain('events/:id/flow');
    expect(appSource).toContain('EventJourneyBuilderPage');
    expect(journeyPageSource).toContain('JourneyFlowCanvas');
    expect(journeyPageSource).toContain('highlightedNodeIds');
  });

  it('already has reusable event aggregate editing and layout primitives for a split journey editor', () => {
    const packageJsonSource = fs.readFileSync(
      path.resolve(__dirname, '../../../package.json'),
      'utf8',
    );
    const eventEditorSource = fs.readFileSync(
      path.resolve(__dirname, 'components/EventEditorPage.tsx'),
      'utf8',
    );

    expect(packageJsonSource).toContain('"react-resizable-panels"');
    expect(eventEditorSource).toContain('buildEventIntakeFromDetail');
    expect(eventEditorSource).toContain('intake_channels');
    expect(eventEditorSource).toContain('TelegramOperationalStatusCard');
    expect(eventEditorSource).toContain("key: 'hub'");
  });

  it('already has a concrete precedent for per-event builder_config editing in the hub module', () => {
    const hubPageSource = fs.readFileSync(
      path.resolve(__dirname, '../hub/HubPage.tsx'),
      'utf8',
    );

    expect(hubPageSource).toContain('builder_config');
    expect(hubPageSource).toContain('HubRenderer');
    expect(hubPageSource).toContain('applyLayoutPreset');
    expect(hubPageSource).toContain('cloneBuilderConfig');
  });

  it('keeps journey domain types decoupled from React Flow primitives', () => {
    const journeyTypesSource = fs.readFileSync(
      path.resolve(__dirname, 'journey/types.ts'),
      'utf8',
    );

    expect(journeyTypesSource).toContain('EventJourneyProjection');
    expect(journeyTypesSource).toContain('EventJourneyUpdatePayload');
    expect(journeyTypesSource).not.toContain('@xyflow/react');
    expect(journeyTypesSource).not.toContain('Node<');
    expect(journeyTypesSource).not.toContain('Edge<');
  });
});
