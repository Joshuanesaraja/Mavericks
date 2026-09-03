import { useEffect } from 'react';
import { useDispatch, useSelector } from 'react-redux';
import { useNavigate } from 'react-router-dom';

import Layout from '../components/Layout';
import { logoutRequest } from '../store/authSlice';
import './Dashboard.css';

function Dashboard() {
  const dispatch = useDispatch();
  const navigate = useNavigate();

  const { isAuthenticated, loading } = useSelector(
    (state) => state.auth
  );

  useEffect(() => {
    if (!isAuthenticated) {
      navigate('/login', { replace: true });
    }
  }, [isAuthenticated, navigate]);

  function handleLogout() {
    dispatch(logoutRequest());
  }

  return (
    <Layout onLogout={handleLogout}>
      <section className="dashboard-header">
        <h2>Dashboard</h2>

        <p>
          Healthcare Management System overview
        </p>
      </section>

      <section className="dashboard-cards">
        <div className="dashboard-card">
          <h3>Patients</h3>
          <p>Manage patient records</p>
          <span>View Patients</span>
        </div>

        <div className="dashboard-card">
          <h3>Appointments</h3>
          <p>Manage schedules and appointments</p>
          <span>View Appointments</span>
        </div>

        <div className="dashboard-card">
          <h3>Prescriptions</h3>
          <p>Manage prescriptions and pharmacy status</p>
          <span>View Prescriptions</span>
        </div>

        <div className="dashboard-card">
          <h3>Calendar</h3>
          <p>View upcoming healthcare events</p>
          <span>View Calendar</span>
        </div>

        <div className="dashboard-card">
          <h3>Messages</h3>
          <p>View communication history</p>
          <span>View Messages</span>
        </div>

        <div className="dashboard-card">
          <h3>Billing</h3>
          <p>Manage invoices and payments</p>
          <span>View Billing</span>
        </div>
      </section>

      {loading && <p>Logging out...</p>}
    </Layout>
  );
}

export default Dashboard;