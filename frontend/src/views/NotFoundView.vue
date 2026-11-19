<template>
  <div class="panel p-4">
    <h1 class="h4">Page not found</h1>
    <p class="text-muted">That page is not part of the boarding house system.</p>
    <RouterLink class="btn btn-primary" :to="{ name: 'dashboard' }">Back to dashboard</RouterLink>
  </div>
</template>
