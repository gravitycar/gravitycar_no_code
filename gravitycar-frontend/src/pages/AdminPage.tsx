import React from 'react';
import CacheManagementPanel from '../components/admin/CacheManagementPanel';

/**
 * AdminPage
 *
 * Top-level wrapper for the admin panel. Section-based layout so future
 * admin features (User Management, System Status, etc.) can be added as
 * additional <section> blocks without restructuring.
 *
 * Layout is applied by App.tsx — no <Layout> wrapper here.
 */
const AdminPage: React.FC = () => {
  return (
    <div className="min-h-screen bg-gray-50">
      <div className="max-w-4xl mx-auto px-4 py-8">
        <h1 className="text-3xl font-bold text-gray-900 mb-8">Administration</h1>

        <div className="space-y-8">
          {/* Cache Management section */}
          <CacheManagementPanel />

          {/* Future admin feature sections can be added here as additional components */}
        </div>
      </div>
    </div>
  );
};

export default AdminPage;
