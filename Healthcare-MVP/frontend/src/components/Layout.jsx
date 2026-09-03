import { useDispatch, useSelector } from "react-redux";
import { useNavigate } from "react-router-dom";

import { logoutRequest } from "../store/authSlice";
import "./Layout.css";

function Layout({ children }) {
  const dispatch = useDispatch();
  const navigate = useNavigate();

  const { user, roles, loading } = useSelector((state) => state.auth);

  function handleLogout() {
    dispatch(logoutRequest());
    navigate("/login", { replace: true });
  }

  return (
    <div className="app-layout">
      <header className="app-header">
        <h1 className="app-brand">Healthcare MVP</h1>

        <nav className="app-nav">
          <button type="button" onClick={() => navigate("/dashboard")}>
            Dashboard
          </button>

          <button type="button" onClick={handleLogout} disabled={loading}>
            {loading ? "Logging out..." : "Logout"}
          </button>
        </nav>
      </header>

      <main className="app-main">
        <div className="app-user">
          <div className="app-user-name">{user?.name || "User"}</div>

          <div className="app-user-role">
            Role: {roles.length > 0 ? roles.join(", ") : "N/A"}
          </div>
        </div>

        {children}
      </main>
    </div>
  );
}

export default Layout;
