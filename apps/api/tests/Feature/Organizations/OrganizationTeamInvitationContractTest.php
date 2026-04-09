<?php

it('creates a pending invitation with token and invitation url instead of an active membership')->todo();

it('dispatches invitation delivery through the current organization default whatsapp instance when requested')->todo();

it('still returns a manual invitation url when the organization has no connected whatsapp instance')->todo();

it('accepts a team invitation without creating a new organization for the invited user')->todo();

it('requires a dedicated ownership transfer flow instead of promoting owners through the generic team invitation endpoint')->todo();

it('lists pending invitations separately from active team memberships in settings')->todo();
