import { useState } from "react";
import { useNavigate } from "react-router-dom";

import { register } from "../auth/authService";
import "./Register.css";

function Register() {
  const navigate = useNavigate();

  const [formData, setFormData] = useState({
    name: "",
    email: "",
    password: "",
    tenant_name: "",
  });

  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");
  const [success, setSuccess] = useState("");

  function handleChange(event) {
    const { name, value } = event.target;

    setFormData((previous) => ({
      ...previous,
      [name]: value,
    }));
  }

  async function handleSubmit(event) {
    event.preventDefault();

    setError("");
    setSuccess("");
    setLoading(true);

    try {
      const response = await register(formData);

      if (!response.success) {
        throw new Error(response.message || "Registration failed");
      }

      setSuccess("Registration successful. You can now login.");

      setFormData({
        name: "",
        email: "",
        password: "",
        tenant_name: "",
      });
    } catch (error) {
      setError(
        error.response?.data?.message || error.message || "Registration failed",
      );
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="register-page">
      <div className="register-card">
        <h1 className="register-title">Create Account</h1>

        <p className="register-subtitle">
          Register your healthcare organization
        </p>

        <form className="register-form" onSubmit={handleSubmit}>
          <div className="form-group">
            <label htmlFor="name">Full Name</label>

            <input
              id="name"
              name="name"
              type="text"
              value={formData.name}
              onChange={handleChange}
              placeholder="Enter your name"
              required
            />
          </div>

          <div className="form-group">
            <label htmlFor="email">Email</label>

            <input
              id="email"
              name="email"
              type="email"
              value={formData.email}
              onChange={handleChange}
              placeholder="Enter your email"
              required
            />
          </div>

          <div className="form-group">
            <label htmlFor="password">Password</label>

            <input
              id="password"
              name="password"
              type="password"
              value={formData.password}
              onChange={handleChange}
              placeholder="Create a password"
              required
            />
          </div>

          <div className="form-group">
            <label htmlFor="tenant_name">Clinic / Organization Name</label>

            <input
              id="tenant_name"
              name="tenant_name"
              type="text"
              value={formData.tenant_name}
              onChange={handleChange}
              placeholder="Enter clinic name"
              required
            />
          </div>

          {error && <p className="register-error">{error}</p>}

          {success && <p className="register-success">{success}</p>}

          <button className="register-button" type="submit" disabled={loading}>
            {loading ? "Creating Account..." : "Create Account"}
          </button>
        </form>

        <button
          className="back-login-button"
          type="button"
          onClick={() => navigate("/login")}
        >
          Back to Login
        </button>
      </div>
    </div>
  );
}

export default Register;
